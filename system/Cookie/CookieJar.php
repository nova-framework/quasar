<?php

namespace Quasar\Cookie;

use Workerman\Protocols\Http;


class CookieJar
{
    /**
     * The default path (if specified).
     *
     * @var string
     */
    protected $path = '/';

    /**
     * The default domain (if specified).
     *
     * @var string
     */
    protected $domain;


    /**
     * Create a new Cookie Jar instance.
     *
     * @param  string  $path
     * @param  string  $domain
     * @return void
     */
    public function __construct($path, $domain)
    {
        $this->path   = $path;
        $this->domain = $domain;
    }

    /**
     * Store an item in the cookie.
     *
     * @param  string  $name
     * @param  string  $value
     * @param  int     $minutes
     * @param  string  $path
     * @param  string  $domain
     * @param  bool    $secure
     * @param  bool    $httpOnly
     * @return bool
     */
    public function set($name, $value, $minutes = 0, $path = null, $domain = null, $secure = false, $httpOnly = true)
    {
        list ($path, $domain) = $this->getPathAndDomain($path, $domain);

        $time = ($minutes == 0) ? 0 : time() + ($minutes * 60);

        //
        $_COOKIE[$name] = $value;

        return Http::setcookie($name, $value, $time, $path, $domain, $secure, $httpOnly);
    }

    /**
     * Retrieve an item from the cookie.
     *
     * @param  string  $key
     * @param  mixed  $default
     * @return  mixed
     */
    public function get($key, $default = null)
    {
        if (isset($_COOKIE[$key]) && ! empty($_COOKIE[$key])) {
            return $_COOKIE[$key];
        }

        return $default;
    }

    /**
     *    Retrieve all items from the cookie.
     *
     *    @return array
     */
    public function all()
    {
        return $_COOKIE;
    }

    /**
     *    Determine if an item exists in the cookie.
     *
     *     @param  string     $key
     *    @return mixed
     */
    public function has($key)
    {
        return ! empty($this->get($key));
    }

    /**
     * Remove an item from the cookie.
     *
     * @param    string     $key
     * @return     bool
     */
    public function delete($key)
    {
        return $this->set($key, '');
    }

    /**
     * Get the path and domain, or the default values.
     *
     * @param  string  $path
     * @param  string  $domain
     * @return array
     */
    protected function getPathAndDomain($path, $domain)
    {
        return array(
            $path   ?: $this->path,
            $domain ?: $this->domain
        );
    }

    /**
     * Set the default path and domain for the jar.
     *
     * @param  string  $path
     * @param  string  $domain
     * @return self
     */
    public function setDefaultPathAndDomain($path, $domain)
    {
        $this->path   = $path;
        $this->domain = $domain;

        return $this;
    }
}
