<?php

namespace Quasar\Http\Controllers;

use Quasar\System\Http\Request;
use Quasar\System\Http\Response;
use Quasar\System\Config;
use Quasar\System\Controller;

use PHPSocketIO\SocketIO;


class Events extends Controller
{
    /**
     * @var \PHPSocketIO\SocketIO;
     */
    protected $socketIo;


    public function __construct(SocketIO $socketIo)
    {
        $this->socketIo = $socketIo;
    }

    public function send(Request $request, $appId)
    {
        if (is_null($header = $request->header('authorization'))) {
            return new Response('400 Bad Request', 400);
        }

        $authKey = str_replace('Bearer ', '', $header);

        if (is_null($secretKey = Config::get('clients.' .$appId))) {
            return new Response('404 Not Found', 404);
        }

        $input = $request->input();

        $hash = hash_hmac('sha256', "POST\n" .$request->path() .':' .json_encode($input), $secretKey, false);

        if ($authKey !== $hash) {
            return new Response('403 Forbidden', 403);
        }

        $channels = json_decode($input['channels'], true);

        $event = str_replace('\\', '.', $input['event']);

        $data = json_decode($input['data'], true);

        // Get the SocketIO's Nsp instance.
        $senderIo = $this->getSenderInstance($appId);

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
    }

    protected function getSenderInstance($appId)
    {
        return $this->socketIo->of($appId);
    }
}
