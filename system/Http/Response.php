<?php

namespace Quasar\Http;

use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http;


class Response
{
    /**
     * @var mixed The content of the Response.
     */
    protected $content = '';

    /**
     * @var int HTTP Status
     */
    protected $status = 200;

    /**
     * @var array Array of HTTP headers
     */
    protected $headers = array();

    /**
     * @var array A listing of HTTP status codes
     */
    public static $statuses = array(
        100 => 'Continue',
        101 => 'Switching Protocols',
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        306 => '(Unused)',
        307 => 'Temporary Redirect',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Request Entity Too Large',
        414 => 'Request-URI Too Long',
        415 => 'Unsupported Media Type',
        416 => 'Requested Range Not Satisfiable',
        417 => 'Expectation Failed',
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
        505 => 'HTTP Version Not Supported'
    );


    public function __construct($content = '', $status = 200, array $headers = array())
    {
        if (isset(self::$statuses[$status])) {
            $this->status = $status;
        }

        $this->headers = $headers;
        $this->content = $content;
    }

    public function send(TcpConnection $connection)
    {
        $protocol = $_SERVER['SERVER_PROTOCOL'];

        $status = $this->status();

        Http::header("$protocol $status " . self::$statuses[$status]);

        foreach ($this->headers as $name => $value) {
            Http::header("$name: $value");
        }

        echo $this->render();

        // Get the output buffer content.
        $content = ob_get_clean();

        return $connection->close($content);
    }

    public function render()
    {
        $content = $this->content();

        if (is_object($content) && method_exists($content, '__toString')) {
            $content = $content->__toString();
        } else {
            $content = (string) $content;
        }

        return trim($content);
    }

    public function header($name, $value)
    {
        $this->headers[$name] = $value;

        return $this;
    }

    public function headers()
    {
        return $this->headers;
    }

    public function status($status = null)
    {
        if (is_null($status)) {
            return $this->status;
        } else if (isset(self::$statuses[$status])) {
            $this->status = $status;
        }

        return $this;
    }

    public function content()
    {
        return $this->content;
    }

    public function __toString()
    {
        return $this->render();
    }
}
