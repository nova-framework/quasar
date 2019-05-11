<?php

namespace Quasar\Http;

use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http;
use Workerman\Worker;

use LogicException;


class FileResponse extends Response
{
    /**
     * @var string The file path of the Response.
     */
    protected $filePath;

    /**
     * @var string The disposition.
     */
    protected $disposition;

    /**
     * Mime mapping.
     *
     * @var array
     */
    protected static $mimeTypeMap = null;


    public function __construct($filePath, $status = 200, array $headers = array(), $disposition = 'inline')
    {
        parent::__construct('', $status, $headers);

        if (! is_readable($filePath)) {
            throw new LogicException('File must be readable.');
        }

        $this->filePath = $filePath;

        $this->disposition = ($disposition === 'inline') ? 'inline' : 'attachment';

        static::initMimeTypeMap();
    }

    /**
     * Init mime map.
     *
     * @return void
     */
    public static function initMimeTypeMap()
    {
        if (isset(self::$mimeTypeMap)) {
            return;
        }

        $mimeFile = Http::getMimeTypesFile();

        if (! is_file($mimeFile)) {
            Worker::log("$mimeFile mime.type file not fond");

            return;
        }

        $items = file($mimeFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if (! is_array($items)) {
            Worker::log("get $mimeFile mime.type content fail");

            return;
        }

        self::$mimeTypeMap = array();

        foreach ($items as $content) {
            if (preg_match("/\s*(\S+)\s+(\S.+)/", $content, $match)) {
                $mimeType = $match[1];

                $extensions = explode(' ', substr($match[2], 0, -1));

                foreach ($extensions as $extension) {
                    self::$mimeTypeMap[$extension] = $mimeType;
                }
            }
        }
    }

    public function send(TcpConnection $connection)
    {
        $filePath = $this->getFilePath();

        // Close the output buffer first.
        ob_end_clean();

        // Check for the status 304.
        $info = stat($filePath);

        $modifiedTime = $info ? date('D, d M Y H:i:s', $info['mtime']) . ' ' . date_default_timezone_get() : '';

        if (! empty($_SERVER['HTTP_IF_MODIFIED_SINCE']) && $info) {
            // Http 304.
            if ($modifiedTime === $_SERVER['HTTP_IF_MODIFIED_SINCE']) {
                Http::header('HTTP/1.1 304 Not Modified');

                $connection->close('');

                return;
            }
        }

        // Http header.
        if ($modifiedTime) {
            $modifiedTime = "Last-Modified: $modifiedTime\r\n";
        }

        $fileSize = filesize($filePath);
        $fileInfo = pathinfo($filePath);

        $extension = isset($fileInfo['extension']) ? $fileInfo['extension'] : '';
        $fileName  = isset($fileInfo['filename'])  ? $fileInfo['filename']  : '';

        $header = "HTTP/1.1 200 OK\r\n";

        if (isset(self::$mimeTypeMap[$extension])) {
            $header .= "Content-Type: " .self::$mimeTypeMap[$extension] ."\r\n";
        } else {
            $header .= "Content-Type: application/octet-stream\r\n";
        }

        $header .= "Content-Disposition: {$this->disposition}; filename=\"$fileName\"\r\n";
        $header .= "Connection: keep-alive\r\n";
        $header .= $modifiedTime;
        $header .= "Content-Length: $fileSize\r\n\r\n";

        $trunkLimitSize = 1024 * 1024;

        if ($fileSize < $trunkLimitSize) {
            return $connection->send($header .file_get_contents($filePath), true);
        }

        $connection->send($header, true);

        // Read the file content from disk piece by piece and send to client.
        $connection->fileHandler = fopen($filePath, 'r');

        $callback = function () use ($connection)
        {
            while (empty($connection->bufferFull)) {
                $buffer = fread($connection->fileHandler, 8192);

                if (($buffer === '') || ($buffer === false)) {
                    return;
                }

                $connection->send($buffer, true);
            }
        };

        // Send buffer full.
        $connection->onBufferFull = function ($connection)
        {
            $connection->bufferFull = true;
        };

        // Send buffer drain.
        $connection->onBufferDrain = function ($connection) use ($callback)
        {
            $connection->bufferFull = false;

            call_user_func($callback);
        };

        call_user_func($callback);
    }

    public function getFilePath()
    {
        return $this->filePath;
    }
}
