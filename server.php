#!/usr/bin/env php
<?php

use Quasar\Platform\Exceptions\NotFoundHttpException;
use Quasar\Platform\Http\Request;
use Quasar\Platform\Http\Response;
use Quasar\Platform\Http\Router;
use Quasar\Platform\Config;
use Quasar\Platform\Container;

use Workerman\Worker;
use PHPSocketIO\SocketIO;


//--------------------------------------------------------------------------
// Global Configuration
//--------------------------------------------------------------------------

defined('DS') || define('DS', DIRECTORY_SEPARATOR);

define('BASEPATH', realpath(__DIR__) .DS);

define('QUASAR_PATH', BASEPATH .'quasar' .DS);

define('STORAGE_PATH', BASEPATH .'storage' .DS);

//--------------------------------------------------------------------------
// Load the Composer Autoloader
//--------------------------------------------------------------------------

require BASEPATH .'vendor' .DS .'autoload.php';


//--------------------------------------------------------------------------
// Load the Configuration
//--------------------------------------------------------------------------

require QUASAR_PATH .'Config.php';

// Load the configuration files.
foreach (glob(QUASAR_PATH .'Config/*.php') as $path) {
    if (! is_readable($path)) continue;

    $key = lcfirst(pathinfo($path, PATHINFO_FILENAME));

    Config::set($key, require_once($path));
}


//--------------------------------------------------------------------------
// Setup the Server
//--------------------------------------------------------------------------

Container::singleton('Quasar\Platform\Exceptions\Handler');


//--------------------------------------------------------------------------
// Create the Push Server
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
    $path = QUASAR_PATH .'Http';

    // Listen on a HTTP port.
    $innerHttpWorker = new Worker('http://' .SERVER_HOST .':' .SERVER_PORT);

    // Create a Router instance.
    $router = new Router(QUASAR_PATH .'Routes.php');

    // Load the HTTP Bootstrap file.
    require QUASAR_PATH .'Bootstrap.php';

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


//--------------------------------------------------------------------------
// Setup the Workerman Environment
//--------------------------------------------------------------------------

if (! file_exists($pidPath = STORAGE_PATH)) {
    mkdir($pidPath, 0755, true);
}

Worker::$pidFile = $pidPath .DS .sha1(__FILE__) .'.pid';

Worker::$logFile = STORAGE_PATH .'logs' .DS .'workerman.log';


//--------------------------------------------------------------------------
// Run all Workers
//--------------------------------------------------------------------------

Worker::runAll();
