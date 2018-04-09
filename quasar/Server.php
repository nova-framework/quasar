<?php

//--------------------------------------------------------------------------
// The Push Server
//--------------------------------------------------------------------------

use Quasar\Platform\Exceptions\FatalThrowableError;
use Quasar\Platform\Http\Request;
use Quasar\Platform\Http\Router;
use Quasar\Platform\Pipeline;

use PHPSocketIO\SocketIO;
use Workerman\Worker;

// Create and setup the PHPSocketIO service.
$app->instance(SocketIO::class, $socketIo = new SocketIO(SOCKET_PORT, array(
    'nsp'    => '\Quasar\Platform\SocketIO\Nsp',
    'socket' => '\Quasar\Platform\SocketIO\Socket',
)));

// Get the clients list, mapping as: appId as key, secretKey as value.
$clients = array_pluck($config->get('clients', array()), 'secret', 'key');

// When the client initiates a connection event, set various event callbacks for connecting sockets.
foreach ($clients as $appKey => $secretKey) {
    $senderIo = $socketIo->of($appKey);

    $senderIo->on('connection', function ($socket) use ($senderIo, $secretKey)
    {
        require QUASAR_PATH .'Events.php';
    });
}

// When $socketIo is started, it listens on an HTTP port, through which data can be pushed to any channel.
$socketIo->on('workerStart', function () use ($app)
{
    // Create a Router instance.
    $router = new Router($app);

    // Load the WEB Bootstrap file.
    require QUASAR_PATH .'Http' .DS .'Bootstrap.php';

    // Listen on a HTTP port.
    $innerHttpWorker = new Worker('http://' .SERVER_HOST .':' .SERVER_PORT);

    // Triggered when HTTP client sends data.
    $innerHttpWorker->onMessage = function ($connection) use ($app, $router)
    {
        $request = Request::createFromGlobals();

        $pipeline = new Pipeline(
            $app,  $app['config']->get('platform.middleware', array())
        );

        try {
            $response = $pipeline->handle($request, function ($request) use ($router)
            {
                return $router->dispatch($request);
            });
        }
        catch (Exception $e) {
            $response = $app['exception']->handleException($request, $e);
        }
        catch (Throwable $e) {
            $response = $app['exception']->handleException($request, new FatalThrowableError($e));
        }

        return $response->send($connection);
    };

    // Perform the monitoring.
    $innerHttpWorker->listen();
});
