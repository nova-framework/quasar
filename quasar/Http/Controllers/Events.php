<?php

namespace Quasar\Http\Controllers;

use Quasar\Platform\Http\Controller;
use Quasar\Platform\Http\Request;
use Quasar\Platform\Http\Response;
use Quasar\Platform\Application;

use PHPSocketIO\SocketIO;


class Events extends Controller
{
    /**
     * @var \Quasar\Platform\Application;
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

    public function send(Request $request, $appId)
    {
        if (! $this->validate($request, $appId)) {
            return new Response('403 Forbidden', 403);
        }

        $channels = json_decode($input['channels'], true);

        $event = str_replace('\\', '.', $input['event']);

        $data = json_decode($input['data'], true);

        // Get the SocketIO's Nsp instance.
        $senderIo = $this->getClientSender($appId);

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

    protected function validate(Request $request, $appId)
    {
        if (is_null($header = $request->header('authorization'))) {
            return false;
        }

        $authKey = str_replace('Bearer ', '', $header);

        if (is_null($secretKey = $this->getClientKey($appId))) {
            return false;
        }

        $hash = hash_hmac('sha256', "POST\n" .$request->path() .':' .json_encode($request->input()), $secretKey, false);

        return ($authKey === $hash);
    }

    protected function getClientKey($appId)
    {
        $config = $this->app['config'];

        return array_get(
            array_pluck($config->get('clients', array()), 'secret', 'appId'), $appId
        );
    }

    protected function getClientSender($appId)
    {
        return $this->socketIo->of($appId);
    }
}
