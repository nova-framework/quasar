#!/usr/bin/env php
<?php

use Quasar\Platform\Exceptions\FatalThrowableError;
use Quasar\Platform\Exceptions\Handler as ExceptionHandler;
use Quasar\Platform\Http\Request;
use Quasar\Platform\Http\Response;
use Quasar\Platform\Http\Router;
use Quasar\Platform\AliasLoader;
use Quasar\Platform\Config;
use Quasar\Platform\Container;
use Quasar\Platform\Pipeline;

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
// Setup the Errors Reporting
//--------------------------------------------------------------------------

error_reporting(-1);

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
// Set The Default Timezone From Configuration
//--------------------------------------------------------------------------

date_default_timezone_set(
    Config::get('platform.timezone', 'Europe/London')
);

//--------------------------------------------------------------------------
// Setup the Server
//--------------------------------------------------------------------------

Container::singleton(ExceptionHandler::class);

// Initialize the Aliases Loader.
AliasLoader::initialize();

//--------------------------------------------------------------------------
// Create the Push Server
//--------------------------------------------------------------------------

// The PHPSocketIO service.
Container::instance(SocketIO::class, $socketIo = new SocketIO(SENDER_PORT));

// Get the Quasar's configured clients.
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
    $middleware = Config::get('platform.middleware', array());

    // Create a Router instance.
    $router = new Router();

    // Load the bootstrap file for WEB.
    require QUASAR_PATH .'Bootstrap.php';

    // Load the HTTP routes.
    $router->loadRoutes(QUASAR_PATH .'Routes.php');

    // Listen on a HTTP port.
    $innerHttpWorker = new Worker('http://' .SERVER_HOST .':' .SERVER_PORT);

    // Triggered when HTTP client sends data.
    $innerHttpWorker->onMessage = function ($connection) use ($router, $middleware)
    {
        $request = Request::createFromGlobals();

        try {
            $pipeline = new Pipeline($middleware);

            $response = $pipeline->dispatch($request, function ($request) use ($router)
            {
                return $router->dispatch($request);
            });
        }
        catch (Exception $e) {
            $handler = Container::make(ExceptionHandler::class);

            $response = $handler->handleException($request, $e);
        }
        catch (Throwable $e) {
            $handler = Container::make(ExceptionHandler::class);

            $response = $handler->handleException($request, new FatalThrowableError($e));
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
