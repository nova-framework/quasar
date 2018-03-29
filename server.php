<?php

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

define('STORAGE_PATH', BASEPATH .'storage' .DS);

//--------------------------------------------------------------------------
// Load the Composer Autoloader
//--------------------------------------------------------------------------

include BASEPATH .'vendor' .DS .'autoload.php';


//--------------------------------------------------------------------------
// Load the Configuration
//--------------------------------------------------------------------------

include BASEPATH .'quasar' .DS .'Config.php';


//--------------------------------------------------------------------------
// Helper Functions
//--------------------------------------------------------------------------

function is_member(array $members, $userId)
{
    return ! empty(array_filter($members, function ($member) use ($userId)
    {
        return $member['userId'] === $userId;
    }));
}


//--------------------------------------------------------------------------
// Create the Push Server
//--------------------------------------------------------------------------

// The PHPSocketIO service.
$socketIo = new SocketIO(SENDER_PORT);

// When the client initiates a connection event, set various event callbacks for connecting sockets.
foreach ($clients as $appId => $secretKey) {
    $senderIo = $socketIo->of($appId);

    $senderIo->presence = array();

    // Include the Events file.
    include BASEPATH .'quasar' .DS .'Events.php';
}

// When $socketIo is started, it listens on an HTTP port, through which data can be pushed to any channel.
$socketIo->on('workerStart', function () use ($socketIo, $clients)
{
    // Listen on a HTTP port.
    $innerHttpWorker = new Worker('http://' .SERVER_HOST .':' .SERVER_PORT);

    // Triggered when HTTP client sends data.
    $innerHttpWorker->onMessage = function ($connection) use ($socketIo, $clients)
    {
        $method = $_SERVER['REQUEST_METHOD'];

        $path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/') ?: '/';

        if ($method !== 'POST') {
            Http::header('HTTP/1.1 405 Method Not Allowed');

            return $connection->close('405 Method Not Allowed');
        }

        // It is a POST request, check its path.
        else if (preg_match('#^apps/([^/]+)/events$#', $path, $matches) !== 1) {
            Http::header('HTTP/1.1 404 Not Found');

            return $connection->close('404 Not Found');
        }

        $appId = $matches[1];

        $authKey = isset($_SERVER['HTTP_AUTHORIZATION'])
            ? str_replace('Bearer ', '', $_SERVER['HTTP_AUTHORIZATION']) : null;

        if (! array_key_exists($appId, $clients) || empty($authKey)) {
            Http::header('HTTP/1.1 400 Bad Request');

            return $connection->close('400 Bad Request');
        }

        $secretKey = $clients[$appId];

        $hash = hash_hmac('sha256', $method ."\n/" .$path .':' .json_encode($_POST), $secretKey, false);

        if ($authKey !== $hash) {
            Http::header('HTTP/1.1 403 Forbidden');

            return $connection->close('403 Forbidden');
        }

        $channels = json_decode($_POST['channels'], true);

        $event = str_replace('\\', '.', $_POST['event']);

        $data = json_decode($_POST['data'], true);

        // Get the Sender instance.
        $senderIo = $socketIo->of($appId);

        // We will try to find the Socket instance when a socketId is specified.
        $socket = null;

        if (! empty($_POST['socketId'])) {
            $socketId = $_POST['socketId'];

            if (isset($senderIo->connected[$socketId])) {
                $socket = $senderIo->connected[$socketId];
            }
        }

        foreach ($channels as $channel) {
            if (! is_null($socket)) {
                // Send the event to other subscribers, excluding this socket.
                $socket->to($channel)->emit($event, $data);
            } else {
                // Send the event to all subscribers from specified channel.
                $senderIo->to($channel)->emit($event, $data);
            }
        }

        Http::header('HTTP/1.1 200 OK');

        return $connection->close('200 OK');
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
