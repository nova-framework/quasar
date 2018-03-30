<?php

use Quasar\System\Exceptions\NotFoundHttpException;
use Quasar\System\Http\Request;
use Quasar\System\Http\Response;
use Quasar\System\Http\Router;
use Quasar\System\Config;
use Quasar\System\Container;

use Workerman\Worker;
use PHPSocketIO\SocketIO;


//--------------------------------------------------------------------------
// The Push Server
//--------------------------------------------------------------------------

// The PHPSocketIO service.
Container::instance(
    SocketIO::class, $socketIo = new SocketIO(SENDER_PORT)
);

// Get the configured clients.
$clients = Config::get('clients');

// When the client initiates a connection event, set various event callbacks for connecting sockets.
foreach ($clients as $appId => $secretKey) {
    $senderIo = $socketIo->of($appId);

    $senderIo->presence = array();

    // Include the Events file.
    require_once QUASAR_PATH .'Events.php';
}

// When $socketIo is started, it listens on an HTTP port, through which data can be pushed to any channel.
$socketIo->on('workerStart', function ()
{
    // Listen on a HTTP port.
    $innerHttpWorker = new Worker('http://' .SERVER_HOST .':' .SERVER_PORT);

    // Create a Router instance.
    $router = new Router(QUASAR_PATH .'Routes.php');

    // Load the HTTP Bootstrap file.
    require QUASAR_PATH .'Http' .DS .'Bootstrap.php';

    // Triggered when HTTP client sends data.
    $innerHttpWorker->onMessage = function ($connection) use ($router)
    {
        try {
            $request = Request::createFromGlobals();

            $response = $router->dispatch($request);
        }
        catch (NotFoundHttpException $e) {
            $response = new Response('404 Not Found', 404);
        }

        return $response->send($connection);
    };

    // Perform the monitoring.
    $innerHttpWorker->listen();
});

