#!/usr/bin/env php
<?php

use Quasar\System\Exceptions\NotFoundHttpException;
use Quasar\System\Config;
use Quasar\System\Container;
use Quasar\System\Request;
use Quasar\System\Response;
use Quasar\System\Router;

use Workerman\Protocols\Http;
use Workerman\Worker;
use Workerman\WebServer;
use Workerman\Lib\Timer;
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
    $key = lcfirst(pathinfo($path, PATHINFO_FILENAME));

    Config::set($key, require($path));
}


//--------------------------------------------------------------------------
// Create the Push Server
//--------------------------------------------------------------------------

// The PHPSocketIO service.
Container::instance(
    SocketIO::class, $socketIo = new SocketIO(SENDER_PORT)
);

$clients = Config::get('clients');

// When the client initiates a connection event, set various event callbacks for connecting sockets.
foreach ($clients as $appId => $secretKey) {
    $senderIo = $socketIo->of($appId);

    $senderIo->presence = array();

    // Include the Events file.
    require QUASAR_PATH .'Events.php';
}

// When $socketIo is started, it listens on an HTTP port, through which data can be pushed to any channel.
$socketIo->on('workerStart', function ()
{
    // Listen on a HTTP port.
    $innerHttpWorker = new Worker('http://' .SERVER_HOST .':' .SERVER_PORT);

    // Triggered when HTTP client sends data.
    $innerHttpWorker->onMessage = function ($connection)
    {
        $router = new Router();

        require QUASAR_PATH .'Routes.php';

        try {
            $request = Request::createFromGlobals();

            $response = $router->dispatch($request);
        }
        catch (NotFoundHttpException $e) {
            $response = new Response('404 Not Found', 404);
        }

        return $response->send($connection);
    };

    // Perform monitoring.
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
