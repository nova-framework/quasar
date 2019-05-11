<?php

namespace Server\Http\Controllers;

use Quasar\Http\Controller;
use Quasar\Http\Request;
use Quasar\Http\Response;
use Quasar\Application;

use PHPSocketIO\SocketIO;


class Events extends Controller
{
    /**
     * @var \Quasar\Application;
     */
    protected $app;

    /**
     * @var \PHPSocketIO\SocketIO;
     */
    protected $socketIo;


    public function __construct(Application $app, SocketIO $socketIo)
    {
        $this->app = $app;

        $this->socketIo = $socketIo;
    }

    public function send(Request $request, $appKey)
    {
        if (is_null($secretKey = array_get($this->getClientKeys(), $appKey))) {
            return new Response('404 Not Found', 404);
        }

        // The requested publicKey is valid.
        else if (! $this->validateRequest($request, $secretKey)) {
            return new Response('403 Forbidden', 403);
        }

        $channels = json_decode($request->input('channels'), true);

        $event = str_replace('\\', '.', $request->input('event'));

        $data = json_decode($request->input('data'), true);

        // Get the SocketIO's Nsp instance.
        $senderIo = $this->getSender($appKey);

        // We will try to find the Socket instance when a socketId is specified.
        if (! empty($socketId = $request->input('socketId'))) {
            $socket = $senderIo->getConnectedSocket($socketId);
        } else {
            $socket = null;
        }

        foreach ($channels as $channel) {
            $eventName = $channel .'#' .$event;

            if (! is_null($socket)) {
                // Send the event to other subscribers, excluding this socket.
                $socket->to($channel)->emit($eventName, $data);
            } else {
                // Send the event to all subscribers from specified channel.
                $senderIo->to($channel)->emit($eventName, $data);
            }
        }

        return new Response('200 OK', 200);
    }

    protected function validateRequest(Request $request, $secretKey)
    {
        if (is_null($header = $request->header('authorization'))) {
            return false;
        }

        $authKey = str_replace('Bearer ', '', $header);

        $value = $request->method() ."\n" .$request->path() .':' .json_encode($request->input());

        return ($authKey === hash_hmac('sha256', $value, $secretKey, false));
    }

    protected function getClientKeys()
    {
        $config = $this->app['config'];

        return array_pluck($config->get('clients', array()), 'secret', 'key');
    }

    protected function getSender($namespace)
    {
        return $this->socketIo->of($namespace);
    }
}
