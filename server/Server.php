<?php

//--------------------------------------------------------------------------
// The Push Server
//--------------------------------------------------------------------------

use Quasar\Http\Request;

use PHPSocketIO\SocketIO;
use Workerman\Worker;

// Get the clients list.
$clients = $config->get('clients', array());

// Create and setup the PHPSocketIO service.
$app->instance(SocketIO::class, $socketIo = new SocketIO(SOCKET_PORT, array(
    'nsp'    => 'Server\Platform\SocketIO\Nsp',
    'socket' => 'Server\Platform\SocketIO\Socket',
)));

//
// When $socketIo is started, it listens on an HTTP port, through which data can be pushed to any channel.

$socketIo->on('workerStart', function () use ($app)
{
    $router = $app['router'];

    // Bootstrap the Router instance.
    require SERVER_PATH .'Http' .DS .'Bootstrap.php';

    // Listen on a HTTP port.
    $innerHttpWorker = new Worker('http://' .SERVER_HOST .':' .SERVER_PORT);

    // Triggered when HTTP client sends data.
    $innerHttpWorker->onMessage = function ($connection) use ($app, $router)
    {
        ob_start();

        // Dispatch the HTTP request via Routing.
        $app->instance('request', $request = Request::createFromGlobals());

        $response = $router->handle($request);

        // First we will send to output the Response content.
        $response->send();

        // Send the response headers and content to TCP Connection instance.
        $response->close($connection, ob_get_clean());
    };

    // Perform the monitoring.
    $innerHttpWorker->listen();
});

// When $socketIo is stopped, execute the associated callback.
$socketIo->on('workerStop', function ()
{
    //
});


//
// When a SocketIO client initiates a connection event, we will set various event callbacks for connecting sockets.

function channelMembersHas(array $members, $id)
{
    return ! empty(array_filter($members, function ($member) use ($id)
    {
        return ($id == $member['id']);
    }));
}

array_walk($clients, function ($client) use ($socketIo)
{
    $publicKey = $client['key']; // We will use the client's public key also as namespace.

    //
    $clientIo = $socketIo->of($publicKey);

    $clientIo->setup(
        $publicKey, $client['secret'], array_get($client, 'options', array())
    );

    $clientIo->on('connection', function ($socket) use ($clientIo)
    {
        // Triggered when the client sends a subscribe event.
        $socket->on('subscribe', function ($channel, $authKey = null, $data = null) use ($socket, $clientIo)
        {
            $channel = (string) $channel;

            //
            $socketId = $socket->id;

            if (preg_match('#^(?:(private|presence)-)?([-a-zA-Z0-9_=@,.;]+)$#', $channel, $matches) !== 1) {
                $clientIo->to($socketId)->emit($channel .'#quasar:subscription_error', 400);

                return;
            }

            $type = ! empty($matches[1]) ? $matches[1] : 'public';

            if ($type == 'public') {
                $socket->join($channel);

                $clientIo->to($socketId)->emit($channel .'#quasar:subscription_succeeded');

                return;
            }

            $result = $clientIo->authorize($socketId, $channel, $type, $authKey, (string) $data);

            if ($result > 0) {
                $clientIo->to($socketId)->emit($channel .'#quasar:subscription_error', $result);

                return;
            }

            $socket->join($channel);

            if ($type == 'private') {
                $clientIo->to($socketId)->emit($channel .'#quasar:subscription_succeeded');

                return;
            }

            // A presence channel additionally needs to store the subscribed member's information.
            else if (! isset($clientIo->presence[$channel])) {
                $clientIo->presence[$channel] = array();
            }

            $members =& $clientIo->presence[$channel];

            // Decode the member information.
            $payload = json_decode($data, true);

            $member = array(
                'id'   => $payload['userId'],
                'info' => $payload['userInfo']
            );

            // Determine if the user is already a member of this channel.
            $alreadyMember = channelMembersHas($members, $member['id']);

            $members[$socketId] = $member;

            // Emit the events associated with the channel subscription.
            $items = array();

            foreach (array_values($members) as $item) {
                if (! array_key_exists($key = $item['id'], $items)) {
                    $items[$key] = $item['info'];
                }
            }

            $data = array(
                'me'      => $member,
                'members' => array_values($items),
            );

            $clientIo->to($socketId)->emit($channel .'#quasar:subscription_succeeded', $data);

            if (! $alreadyMember) {
                $socket->to($channel)->emit($channel .'#quasar:member_added', $member);
            }
        });

        // Triggered when the client sends a unsubscribe event.
        $socket->on('unsubscribe', function ($channel) use ($socket, $clientIo)
        {
            $channel = (string) $channel;

            if ((strpos($channel, 'presence-') === 0) && isset($clientIo->presence[$channel])) {
                $members =& $clientIo->presence[$channel];

                if (array_key_exists($socketId = $socket->id, $members)) {
                    $member = array_pull($members, $socketId);

                    if (! channelMembersHas($members, $member['id'])) {
                        $socket->to($channel)->emit($channel .'#quasar:member_removed', $member);
                    }
                }

                if (empty($members)) {
                    unset($clientIo->presence[$channel]);
                }
            }

            $socket->leave($channel);
        });

        // Triggered when the client sends a message event.
        $socket->on('channel:event', function ($channel, $event, $data) use ($socket)
        {
            $channel = (string) $channel;

            if (preg_match('#^(private|presence)-(.*)#', $channel) !== 1) {
                // The requested channel is not a private one.
                return;
            }

            // If it is a client event and socket joined the channel, we will emit this event.
            else if ((preg_match('#^client-(.*)$#', $event) === 1) && isset($socket->rooms[$channel])) {
                $eventName = $channel .'#' .$event;

                $socket->to($channel)->emit($eventName, $data);
            }
        });

        // When the client is disconnected is triggered (usually caused by closing the web page or refresh)
        $socket->on('disconnect', function () use ($socket, $clientIo)
        {
            $socketId = $socket->id;

            foreach ($clientIo->presence as $channel => &$members) {
                if (! array_key_exists($socketId, $members)) {
                    continue;
                }

                $member = array_pull($members, $socketId);

                if (! channelMembersHas($members, $member['id'])) {
                    $socket->to($channel)->emit('quasar:member_removed', $channel, $member);
                }

                if (empty($members)) {
                    unset($clientIo->presence[$channel]);
                }
            }
        });
    });
});
