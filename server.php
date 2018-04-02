#!/usr/bin/env php
<?php

use Quasar\Platform\Http\FileResponse;
use Quasar\Platform\AliasLoader;
use Quasar\Platform\Container;
use Quasar\Platform\Application;
use Quasar\Platform\Config;

use Workerman\Protocols\Http;
use Workerman\Worker;
use PHPSocketIO\SocketIO;


//--------------------------------------------------------------------------
// Global Defines
//--------------------------------------------------------------------------

defined('DS') || define('DS', DIRECTORY_SEPARATOR);

define('BASEPATH', realpath(__DIR__) .DS);

define('QUASAR_PATH', BASEPATH .'quasar' .DS);


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
// Initialize The FileResponse's MimeTypes
//--------------------------------------------------------------------------

FileResponse::initMimeTypeMap();


//--------------------------------------------------------------------------
// Load The Global Configuration
//--------------------------------------------------------------------------

require QUASAR_PATH .'Config.php';


//--------------------------------------------------------------------------
// Create The Application
//--------------------------------------------------------------------------

$app = new Application();

// Setup the Application instance.
$app->instance('app', $app);

$app->bindInstallPaths(array(
    'base'    => BASEPATH,
    'quasar'  => QUASAR_PATH,
    'storage' => STORAGE_PATH,
));


//--------------------------------------------------------------------------
// Set The Global Container Instance
//--------------------------------------------------------------------------

Container::setInstance($app);


//--------------------------------------------------------------------------
// Create The Config Instance
//--------------------------------------------------------------------------

$app->instance('config', $config = new Config());


//--------------------------------------------------------------------------
// Load The Platform Configuration
//--------------------------------------------------------------------------

foreach (glob(QUASAR_PATH .'Config/*.php') as $path) {
    if (! is_readable($path)) continue;

    $key = lcfirst(pathinfo($path, PATHINFO_FILENAME));

    $config->set($key, require_once($path));
}


//--------------------------------------------------------------------------
// Set The Default Timezone
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
// Setup The Workerman's Environment
//--------------------------------------------------------------------------

Worker::$pidFile = STORAGE_PATH .'workers' .DS .sha1(__FILE__) .'.pid';

Worker::$logFile = STORAGE_PATH .'logs' .DS .'platform.log';


//--------------------------------------------------------------------------
// Setup The Workerman's Session
//--------------------------------------------------------------------------

Http::sessionSavePath(
    $config->get('session.files', STORAGE_PATH .'sessions')
);

Http::sessionName(
    $config->get('session.cookie', 'quasar_session')
);


//--------------------------------------------------------------------------
// Load the Push Server
//--------------------------------------------------------------------------

require QUASAR_PATH .'Server.php';


//--------------------------------------------------------------------------
// Run All Workers
//--------------------------------------------------------------------------

Worker::runAll();
