#!/usr/bin/env php
<?php

use Quasar\Platform\Database\Manager as DatabaseManager;
use Quasar\Platform\Events\Dispatcher as EventDispatcher;
use Quasar\Platform\Exceptions\FatalThrowableError;
use Quasar\Platform\Exceptions\Handler as ExceptionHandler;
use Quasar\Platform\Http\FileResponse;
use Quasar\Platform\Http\Request;
use Quasar\Platform\Http\Response;
use Quasar\Platform\Http\Router;
use Quasar\Platform\Session\Store as SessionStore;
use Quasar\Platform\View\Factory as ViewFactory;
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
// Initialize the FileResponse's mime types
//--------------------------------------------------------------------------

FileResponse::initMimeTypeMap();

//--------------------------------------------------------------------------
// Setup the Container
//--------------------------------------------------------------------------

$container = new Container();

// Setup the Container.
$container->setInstance($container);

$container->instance(Container::class, $container);

// Setup the Config instance.
$container->instance(array(Config::class, 'config'), $config = new Config());

// Setup the singleton classes.
$container->singleton(array(DatabaseManager::class, 'database'));
$container->singleton(array(ExceptionHandler::class, 'exception'));
$container->singleton(array(EventDispatcher::class, 'events'));
$container->singleton(array(SessionStore::class, 'session'));
$container->singleton(array(ViewFactory::class, 'view'));


//--------------------------------------------------------------------------
// Load the Configuration
//--------------------------------------------------------------------------

require QUASAR_PATH .'Config.php';

// Load the configuration files.
foreach (glob(QUASAR_PATH .'Config/*.php') as $path) {
    if (! is_readable($path)) continue;

    $key = lcfirst(pathinfo($path, PATHINFO_FILENAME));

    $config->set($key, require_once($path));
}

//--------------------------------------------------------------------------
// Set The Default Timezone From Configuration
//--------------------------------------------------------------------------

date_default_timezone_set(
    $config->get('platform.timezone', 'Europe/London')
);

//--------------------------------------------------------------------------
// Setup the Server
//--------------------------------------------------------------------------

AliasLoader::initialize(
    $config->get('platform.aliases', array())
);

//--------------------------------------------------------------------------
// Create the Push Server
//--------------------------------------------------------------------------

// Create and setup the PHPSocketIO service.
$container->instance(SocketIO::class, $socketIo = new SocketIO(SENDER_PORT));

// When the client initiates a connection event, set various event callbacks for connecting sockets.
foreach ($config->get('clients') as $appId => $secretKey) {
    $senderIo = $socketIo->of($appId);

    $senderIo->presence = array();

    $senderIo->on('connection', function ($socket) use ($senderIo, $secretKey)
    {
        require_once QUASAR_PATH .'Events.php';
    });
}

// When $socketIo is started, it listens on an HTTP port, through which data can be pushed to any channel.
$socketIo->on('workerStart', function () use ($container)
{
    // Create a Router instance.
    $router = new Router($container);

    // Load the WEB bootstrap.
    require QUASAR_PATH .'Bootstrap.php';

    // Load the WEB routes.
    require QUASAR_PATH .'Routes.php';

    // Listen on a HTTP port.
    $innerHttpWorker = new Worker('http://' .SERVER_HOST .':' .SERVER_PORT);

    // Triggered when HTTP client sends data.
    $innerHttpWorker->onMessage = function ($connection) use ($container, $router)
    {
        $request = Request::createFromGlobals();

        $pipeline = new Pipeline(
            $container,  $container['config']->get('platform.middleware', array())
        );

        try {
            $response = $pipeline->handle($request, function ($request) use ($router)
            {
                return $router->dispatch($request);
            });
        }
        catch (Exception $e) {
            $handler = $container->make(ExceptionHandler::class);

            $response = $handler->handleException($request, $e);
        }
        catch (Throwable $e) {
            $handler = $container->make(ExceptionHandler::class);

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

Worker::$pidFile = STORAGE_PATH .sha1(__FILE__) .'.pid';

Worker::$logFile = STORAGE_PATH .'logs' .DS .'workerman.log';


//--------------------------------------------------------------------------
// Run all Workers
//--------------------------------------------------------------------------

Worker::runAll();
