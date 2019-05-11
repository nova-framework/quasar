<?php

namespace Server\Socket\Platform;

use PHPSocketIO\Nsp as BaseNsp;
use Workerman\Connection\AsyncTcpConnection;
use Workerman\Lib\Timer;

use InvalidArgumentException;


class Nsp extends BaseNsp
{
    /**
     * @var string
     */
    protected $publicKey;

    /**
     * @var string
     */
    protected $secretKey;

    /**
     * @var array
     */
    protected $options = array();

    /**
     * @var array
     */
    public $presence = array();


    /**
     * Initialize the NSP instance.
     *
     * @param string $publicKey
     * @param string $secretKey
     * @param array  $options
     *
     * @return \System\SocketIO\Nsp
     */
    public function setup($publicKey, $secretKey, array $options = array())
    {
        $this->secretKey = $secretKey;
        $this->options   = $options;

        return $this;
    }

    /**
     * Returns the client's public key if any.
     *
     * @return string|null
     */
    public function getPublicKey()
    {
        return $this->publicKey;
    }

    /**
     * Returns the client's secret key if any.
     *
     * @return string|null
     */
    public function getSecretKey()
    {
        return $this->secretKey;
    }

    /**
     * Returns the client's options.
     *
     * @return string|null
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * Returns the connected Socket instance if exists.
     *
     * @return \PHPSocketIO\Socket|null
     */
    public function getConnectedSocket($socketId)
    {
        if (isset($this->connected[$socketId])) {
            return $this->connected[$socketId];
        }
    }

    /**
     * Sends signed HTTP requests to the specified API end-point
     *
     * @param string $url
     * @param string $appKey
     * @param string $secret
     * @param string $body
     *
     * @return bool|void
     * @throws \InvalidArgumentException
     */
    protected function sendHttpRequest($url, $appKey, $secretKey, $body)
    {
        if (empty($components = parse_url($url))) {
            echo new InvalidArgumentException('Bad remote address');

            return false;
        }

        if (isset($components['scheme']) && ($components['scheme'] === 'https')) {
            $scheme = 'ssl';
        } else {
            $scheme = 'tcp';
        }

        $host = $components['host'];

        $port = array_get($components, 'port', 80);

        $path = array_get($components, 'path', '/');

        if (! empty($query = array_get($components, 'query', ''))) {
            $query = '?' .$query;
        }

        $uri = "{$path}{$query}";

        $signature = hash_hmac('sha256', $body, $secretKey, false);

        // Prepare the HTTP Headers.
        $header  = "POST $uri HTTP/1.0\r\n";
        $header .= "Host: $host\r\n";
        $header .= "Connection: close\r\n";
        $header .= "X-Quasar-Key: $appKey\r\n";
        $header .= "X-Quasar-Signature: $signature\r\n";
        $header .= "Content-Type: application/json\r\n";
        $header .= "Content-Length: " . strlen($body);

        // Create the request content.
        $buffer = $header ."\r\n\r\n" .$body;

        // Create a new AsyncTcpConnection instance.
        $client = new AsyncTcpConnection($scheme .'://' .$host .':' .$port);

        $client->onConnect = function ($client) use ($buffer)
        {
            $client->send($buffer);
        };

        $client->onMessage = function($client, $buffer)
        {
            echo $buffer ."\n\n";
        };

        // Close the connection 10 seconds after.
        Timer::add(10, array($client, 'close'), null, false);

        $client->connect();
    }
}
