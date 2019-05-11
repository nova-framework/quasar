<?php

namespace Quasar\Http;


class Request
{
    protected static $instance;

    //
    protected $method;
    protected $headers;
    protected $server;
    protected $query;
    protected $post;
    protected $files;
    protected $cookies;


    public function __construct($method, array $headers, array $server, array $query, array $post, array $files, array $cookies)
    {
        $this->method = strtoupper($method);

        $this->headers = array_change_key_case($headers);

        //
        $this->server  = $server;
        $this->query   = $query;
        $this->post    = $post;
        $this->files   = $files;
        $this->cookies = $cookies;
    }

    public static function createFromGlobals()
    {
        // Get the HTTP method.
        $method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET';

        if (isset($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'])) {
            $method = $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'];
        } else if (isset($_REQUEST['_method'])) {
            $method = $_REQUEST['_method'];
        }

        // Get the server headers.
        $server = array_replace(array(
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => 80,
            'HTTP_HOST' => 'localhost',
            'HTTP_USER_AGENT' => 'Quasar/1.X',
            'HTTP_ACCEPT' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'HTTP_ACCEPT_LANGUAGE' => 'en-us,en;q=0.5',
            'HTTP_ACCEPT_CHARSET' => 'ISO-8859-1,utf-8;q=0.7,*;q=0.7',
            'REMOTE_ADDR' => '127.0.0.1',
            'SCRIPT_NAME' => '',
            'SCRIPT_FILENAME' => '',
            'SERVER_PROTOCOL' => 'HTTP/1.1',
            'REQUEST_TIME' => time(),

        ), $_SERVER);

        // Get the request headers.
        $headers = array();

        $contentHeaders = array('CONTENT_LENGTH' => true, 'CONTENT_MD5' => true, 'CONTENT_TYPE' => true);

        foreach ($server as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                $key = substr($key, 5);
            }

            // CONTENT_* are not prefixed with HTTP_
            else if (! isset($contentHeaders[$key])) {
                continue;
            }

            $headers[$key] = is_numeric($value) ? intval($value) : $value;
        }

        return new static($method, $headers, $server, $_GET, $_POST, $_FILES, $_COOKIE);
    }

    public function instance()
    {
        return $this;
    }

    public function method()
    {
        return $this->method;
    }

    public function path()
    {
        return trim(parse_url($this->server['REQUEST_URI'], PHP_URL_PATH), '/') ?: '/';
    }

    public function ip()
    {
        if (! empty($this->server['HTTP_CLIENT_IP'])) {
            return $this->server['HTTP_CLIENT_IP'];
        } else if (! empty($this->server['HTTP_X_FORWARDED_FOR'])) {
            return $this->server['HTTP_X_FORWARDED_FOR'];
        }

        return $this->server['REMOTE_ADDR'];
    }

    public static function ajax()
    {
        if (! is_null($header = array_get($this->server, 'HTTP_X_REQUESTED_WITH'))) {
            return strtolower($header) === 'xmlhttprequest';
        }

        return false;
    }

    public function previous()
    {
        return array_get($this->server, 'HTTP_REFERER');
    }

    public function header($key)
    {
        return array_get($this->headers, $key);
    }

    public function server($key = null)
    {
        if (is_null($key)) {
            return $this->server;
        }

        return array_get($this->server, $key);
    }

    public function input($key = null, $default = null)
    {
        $input = ($this->method == 'GET') ? $this->query : $this->post;

        return array_get($input, $key, $default);
    }

    public function query($key = null, $default = null)
    {
        return array_get($this->query, $key, $default);
    }

    public function files()
    {
        return $this->files;
    }

    public function file($key)
    {
        return array_get($this->files, $key);
    }

    public function hasFile($key)
    {
        return array_has($this->files, $key);
    }

    public function cookies()
    {
        return $this->cookies;
    }

    public function cookie($key, $default = null)
    {
        return array_get($this->cookies, $key, $default);
    }

    public function hasCookie($key)
    {
        return array_has($this->cookies, $key);
    }
}
