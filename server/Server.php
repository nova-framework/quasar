<?php

//--------------------------------------------------------------------------
// The Push Server
//--------------------------------------------------------------------------

use System\Exceptions\FatalThrowableError;
use System\Http\Request;

use PHPSocketIO\SocketIO;
use Workerman\Worker;


// Create and setup the PHPSocketIO service.
$app->instance(SocketIO::class, $socketIo = new SocketIO(SOCKET_PORT, array(
    'nsp'    => 'Server\Platform\SocketIO\Nsp',
    'socket' => 'Server\Platform\SocketIO\Socket',
)));

// Get the clients list.
$clients = $config->get('clients', array());

// When the client initiates a connection event, set various event callbacks for connecting sockets.
foreach ($clients as $client) {
    $publicKey = $client['key']; // We will use the client's public key as namespace.

    $senderIo = $socketIo->of($publicKey);

    $senderIo->setup(
        $publicKey, $client['secret'], array_get($client, 'options', array())
    );

    $senderIo->on('connection', function ($socket) use ($senderIo)
    {
        require SERVER_PATH .'Events.php';
    });
}

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
