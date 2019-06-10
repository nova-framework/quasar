<?php

use Quasar\Container\Container;
use Quasar\Http\FileResponse;
use Quasar\AliasLoader;
use Quasar\Application;
use Quasar\Config;

use Workerman\Protocols\Http;
use Workerman\Worker;


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

require SERVER_PATH .'Config.php';


//--------------------------------------------------------------------------
// Create The Application
//--------------------------------------------------------------------------

$app = new Application();

// Setup the Application instance.
$app->instance('app', $app);

$app->bindInstallPaths(array(
    'base'    => BASEPATH,
    'quasar'  => QUASAR_PATH,
    'server'  => SERVER_PATH,
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

$paths = glob(SERVER_PATH .'Config/*.php');

array_walk($paths, function ($path) use ($config)
{
    if (is_readable($path)) {
        $name = pathinfo($path, PATHINFO_FILENAME);

        $config->set(lcfirst($name), require_once($path));
    }
});


//--------------------------------------------------------------------------
// Set The Default Timezone
//--------------------------------------------------------------------------

date_default_timezone_set(
    $config->get('server.timezone', 'Europe/London')
);


//--------------------------------------------------------------------------
// Register The Service Providers
//--------------------------------------------------------------------------

$app->getProviderRepository()->load(
    $app, $providers = $config->get('server.providers', array())
);


//--------------------------------------------------------------------------
// Register The Alias Loader
//--------------------------------------------------------------------------

AliasLoader::getInstance(
    $config->get('server.aliases', array())

)->register();


//--------------------------------------------------------------------------
// Setup The Workerman's Environment
//--------------------------------------------------------------------------

Worker::$pidFile = STORAGE_PATH .'workers' .DS .sha1(__FILE__) .'.pid';

Worker::$logFile = STORAGE_PATH .'logs' .DS .'server.log';


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

require SERVER_PATH .'Server.php';


//--------------------------------------------------------------------------
// Return the Application
//--------------------------------------------------------------------------

return $app;
