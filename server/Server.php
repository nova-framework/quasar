<?php

//--------------------------------------------------------------------------
// The Push Server
//--------------------------------------------------------------------------

use System\Http\Request;

use PHPSocketIO\SocketIO;
use Workerman\Worker;

// Get the clients list.
$clients = $config->get('clients', array());

// Create and setup the PHPSocketIO service.
$app->instance(SocketIO::class, $socketIo = new SocketIO(SOCKET_PORT, array(
    'nsp'    => 'Server\Platform\SocketIO\Nsp',
    'socket' => 'Server\Platform\SocketIO\Socket',
)));


// When $socketIo is started, it listens on an HTTP port, through which data can be pushed to any channel.
$socketIo->on('workerStart', function () use ($app)
{
    $router = $app['router'];

    // Bootstrap the Router instance.
    require SERVER_PATH .'Http' .DS .'Bootstrap.php';

    // Listen on a HTTP port.
    $innerHttpWorker = new Worker('http://' .SERVER_HOST .':' .SERVER_PORT);

    // Triggered when HTTP client sends data.
    $innerHttpWorker->onMessage = function ($connection) use ($router)
    {
        ob_start();

        // Dispatch the HTTP request via Routing.
        $request = Request::createFromGlobals();

        $response = $router->handle($request);

        // Render the Response content.
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

array_walk($clients, function ($client) use ($socketIo)
{
    $publicKey = $client['key']; // We will use the client's public key also as namespace.

    //
    $senderIo = $socketIo->of($publicKey);

    $senderIo->setup(
        $publicKey, $client['secret'], array_get($client, 'options', array())
    );

    $senderIo->on('connection', function ($socket) use ($senderIo)
    {
        // Triggered when the client sends a subscribe event.
        $socket->on('subscribe', function ($channel, $authKey = null, $data = null) use ($socket, $senderIo)
        {
            $channel = (string) $channel;

            $successEvent = $channel .'#quasar:subscription_succeeded';
            $errorEvent   = $channel .'#quasar:subscription_error';

            //
            $socketId = $socket->id;

            if (preg_match('#^(?:(private|presence)-)?([-a-zA-Z0-9_=@,.;]+)$#', $channel, $matches) !== 1) {
                $senderIo->to($socketId)->emit($errorEvent, 400);

                return;
            }

            $type = ! empty($matches[1]) ? $matches[1] : 'public';

            if ($type == 'public') {
                $socket->join($channel);

                $senderIo->to($socketId)->emit($successEvent);

                return;
            } else if (empty($authKey)) {
                $senderIo->to($socketId)->emit($errorEvent, 400);

                return;
            }

            $secretKey = $senderIo->getSecretKey();

            if ($type == 'private') {
                $hash = hash_hmac('sha256', $socketId .':' .$channel, $secretKey, false);
            }

            // A presence channel must have a non empty data argument.
            else if (empty($data)) {
                $senderIo->to($socketId)->emit($errorEvent, 400);

                return;
            } else { // presence channel
                $hash = hash_hmac('sha256', $socketId .':' .$channel .':' .$data, $secretKey, false);
            }

            if ($hash !== $authKey) {
                $senderIo->to($socketId)->emit($errorEvent, 403);

                return;
            }

            $socket->join($channel);

            if ($type == 'private') {
                $senderIo->to($socketId)->emit($successEvent);

                return;
            }

            // A presence channel additionally needs to store the subscribed member's information.
            else if (! isset($senderIo->presence[$channel])) {
                $senderIo->presence[$channel] = array();
            }

            $members =& $senderIo->presence[$channel];

            // Decode the member information.
            $payload = json_decode($data, true);

            $member = array(
                'id'   => $payload['userId'],
                'info' => $payload['userInfo']
            );

            // Determine if the user is already a member of this channel.
            $userId = $member['id'];

            $alreadyMember = ! empty(array_filter($members, function ($member) use ($userId)
            {
                return $member['id'] == $userId;
            }));

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

            $senderIo->to($socketId)->emit($successEvent, $data);

            if (! $alreadyMember) {
                $socket->to($channel)->emit($channel .'#quasar:member_added', $member);
            }
        });

        // Triggered when the client sends a unsubscribe event.
        $socket->on('unsubscribe', function ($channel) use ($socket, $senderIo)
        {
            $socketId = $socket->id;

            $channel = (string) $channel;

            if ((strpos($channel, 'presence-') === 0) && isset($senderIo->presence[$channel])) {
                $members =& $senderIo->presence[$channel];

                if (array_key_exists($socketId, $members)) {
                    $member = array_pull($members, $socketId);

                    // Determine if the user is still a member of this channel.
                    $userId = $member['id'];

                    $isMember = ! empty(array_filter($members, function ($member) use ($userId)
                    {
                        return $member['id'] == $userId;
                    }));

                    if (! $isMember) {
                        $socket->to($channel)->emit($channel .'#quasar:member_removed', $member);
                    }
                }

                if (empty($senderIo->presence[$channel])) {
                    unset($senderIo->presence[$channel]);
                }
            }

            $socket->leave($channel);
        });

        // Triggered when the client sends a message event.
        $socket->on('channel:event', function ($channel, $event, $data) use ($socket)
        {
            if (preg_match('#^(private|presence)-(.*)#', $channel) !== 1) {
                // The requested channel is not a private one.
                return;
            }

            // If it is a client event and socket joined the channel, we will emit this event.
            if ((preg_match('#^client-(.*)$#', $event) === 1) && isset($socket->rooms[$channel])) {
                $eventName = $channel .'#' .$event;

                $socket->to($channel)->emit($eventName, $data);
            }
        });

        // When the client is disconnected is triggered (usually caused by closing the web page or refresh)
        $socket->on('disconnect', function () use ($socket, $senderIo)
        {
            $socketId = $socket->id;

            foreach ($senderIo->presence as $channel => &$members) {
                if (! array_key_exists($socketId, $members)) {
                    continue;
                }

                $member = array_pull($members, $socketId);

                // Determine if the user is still a member of this channel.
                $userId = $member['id'];

                $isMember = ! empty(array_filter($members, function ($member) use ($userId)
                {
                    return $member['id'] == $userId;
                }));

                if (! $isMember) {
                    $socket->to($channel)->emit('quasar:member_removed', $channel, $member);
                }

                if (empty($senderIo->presence[$channel])) {
                    unset($senderIo->presence[$channel]);
                }
            }
        });
    });
});
