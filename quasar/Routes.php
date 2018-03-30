<?php

use Quasar\System\Config;
use Quasar\System\Container;
use Quasar\System\Request;
use Quasar\System\Response;

use PHPSocketIO\SocketIO;


$router->post('apps/{appId}/events', function (Request $request, $appId)
{
    if (is_null($secretKey = Config::get('clients.' .$appId))) {
        return new Response('404 Not Found', 404);
    }

    // We got and valid appId.
    else if (! is_null($header = $request->header('authorization'))) {
        $authKey = str_replace('Bearer ', '', $header);
    } else {
        return new Response('400 Bad Request', 400);
    }

    $input = $request->input();

    $hash = hash_hmac('sha256', "POST\n" .$request->path() .':' .json_encode($input), $secretKey, false);

    if ($authKey !== $hash) {
        return new Response('403 Forbidden', 403);
    }

    $channels = json_decode($input['channels'], true);

    $event = str_replace('\\', '.', $input['event']);

    $data = json_decode($input['data'], true);

    //
    // Get the SocketIO instance.
    $socketIo = Container::make(SocketIO::class);

    // Get the Sender instance.
    $senderIo = $socketIo->of($appId);

    // We will try to find the Socket instance when a socketId is specified.
    if (! empty($socketId = $input['socketId']) && isset($senderIo->connected[$socketId])) {
        $socket = $senderIo->connected[$socketId];
    } else {
        $socket = null;
    }

    foreach ($channels as $channel) {
        if (! is_null($socket)) {
            // Send the event to other subscribers, excluding this socket.
            $socket->to($channel)->emit($event, $data);
        } else {
            // Send the event to all subscribers from specified channel.
            $senderIo->to($channel)->emit($event, $data);
        }
    }

    return new Response('200 OK', 200);
});
