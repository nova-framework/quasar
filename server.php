#!/usr/bin/env php
<?php

use Quasar\Platform\Exceptions\FatalThrowableError;
use Quasar\Platform\Http\FileResponse;
use Quasar\Platform\Http\Request;
use Quasar\Platform\Http\Response;
use Quasar\Platform\Http\Router;
use Quasar\Platform\AliasLoader;
use Quasar\Platform\Application;
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
// Load The Composer Autoloader
//--------------------------------------------------------------------------

require BASEPATH .'vendor' .DS .'autoload.php';


//--------------------------------------------------------------------------
// Setup the Errors Reporting
//--------------------------------------------------------------------------

error_reporting(-1);


//--------------------------------------------------------------------------
// Set internal character encoding
//--------------------------------------------------------------------------

if (function_exists('mb_internal_encoding')) {
        mb_internal_encoding('utf-8');
}


//--------------------------------------------------------------------------
// Initialize the FileResponse's mime types
//--------------------------------------------------------------------------

FileResponse::initMimeTypeMap();

//--------------------------------------------------------------------------
// Setup The Application
//--------------------------------------------------------------------------

$app = new Application();

// Setup the Application.
$app->instance('app', $app);

$app->bindInstallPaths(array(
    'base'    => BASEPATH,
    'quasar'  => QUASAR_PATH,
    'storage' => STORAGE_PATH,
));

// Setup the global Container instance.
Container::setInstance($app);

// Setup the Config instance.
$app->instance('config', $config = new Config());


//--------------------------------------------------------------------------
// Load The Configuration
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
// Register The Service Providers
//--------------------------------------------------------------------------

$app->getProviderRepository()->load(
    $app, $providers = $config->get('platform.providers', array())
);


//--------------------------------------------------------------------------
// Register The Alias Loader
//--------------------------------------------------------------------------

AliasLoader::getInstance(
    $config->get('platform.aliases', array())

)->register();

//--------------------------------------------------------------------------
// Create the Push Server
//--------------------------------------------------------------------------

// Create and setup the PHPSocketIO service.
$app->instance(SocketIO::class, $socketIo = new SocketIO(SENDER_PORT));

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
$socketIo->on('workerStart', function () use ($app)
{
    // Create a Router instance.
    $router = new Router($app);

    // Load the WEB bootstrap.
    require QUASAR_PATH .'Bootstrap.php';

    // Load the WEB routes.
    require QUASAR_PATH .'Routes.php';

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


//--------------------------------------------------------------------------
// Setup The Workerman Environment
//--------------------------------------------------------------------------

Worker::$pidFile = STORAGE_PATH .sha1(__FILE__) .'.pid';

Worker::$logFile = STORAGE_PATH .'logs' .DS .'workerman.log';


//--------------------------------------------------------------------------
// Run All Workers
//--------------------------------------------------------------------------

Worker::runAll();
