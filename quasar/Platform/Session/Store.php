<?php

namespace Quasar\Platform\Session;

use Workerman\Protocols\Http;

use ArrayAccess;


class Store implements ArrayAccess
{

    /**
     * Start the session.
     *
     * @return \Quasar\Platform\Session\Store
     */
    public function start()
    {
        if (! $this->getId()) {
            Http::sessionStart();
        }

        if (! $this->has('_token')) {
            $this->regenerateToken();
        }

        return $this;
    }

    /**
     * Get the current session id.
     *
     * @return string
     */
    public function getId()
    {
        return Http::sessionId();
    }

    /**
     * Set a key / value pair or array of key / value pairs in the session.
     *
     * @param  string|array  $key
     * @param  mixed|null    $value
     * @return void
     */
    public function set($key, $value = null)
    {
        if (! is_array($key)) {
            $key = array($key => $value);
        }

        foreach ($key as $innerKey => $innerValue) {
            array_set($_SESSION, $innerKey, $innerValue);
        }
    }

    /**
     * Push a value onto an array session value.
     *
     * @param  string  $key
     * @param  string  $value
     * @return void
     */
    public function push($key, $value)
    {
        $items = $this->get($key, array());

        $items[] = $value;

        $this->set($key, $items);
    }

    /**
     * Flash a key / value pair to the session.
     *
     * @param  string  $key
     * @param  mixed   $value
     * @return void
     */
    public function flash($key, $value)
    {
        $this->set($key, $value);

        $this->push('flash', $key);
    }


    /**
     * Delete all the flashed data.
     *
     * @return void
     */
    public function deleteFlash()
    {
        foreach ($this->get('flash', array()) as $key) {
            $this->delete($key);
        }

        $this->set('flash', array());
    }

    /**
     * Retrieve an item from the session.
     *
     * @param  string  $name
     * @param  mixed   $default
     * @return mixed
     */
    public function get($name, $default = null)
    {
        return array_get($_SESSION, $name, $default);
    }

    /**
     * Retrieve all items from the session.
     *
     * @return array
     */
    public function all()
    {
        return $_SESSION;
    }

    /**
     * Determine if an item exists in the session.
     *
     * @param  string  $name
     * @return mixed
     */
    public function has($name)
    {
        return array_has($_SESSION, $name);
    }

    /**
     * Remove an item from the session.
     *
     * @param  string  $key
     * @return void
     */
    public function delete($key)
    {
        array_forget($_SESSION, $key);
    }

    /**
     * Remove all items from the session.
     *
     * @return bool
     */
    public function flush()
    {
        return $_SESSION = array();
    }

    /**
     * Get CSRF token value.
     *
     * @return void
     */
    public function token()
    {
        return $this->get('_token');
    }

    /**
     * Regenerate the CSRF token value.
     *
     * @return void
     */
    public function regenerateToken()
    {
        $this->set('_token', str_random(40));
    }

    /**
     * Determine if the given configuration option exists.
     *
     * @param  string  $key
     * @return bool
     */
    public function offsetExists($key)
    {
        return $this->has($key);
    }

    /**
     * Get a configuration option.
     *
     * @param  string  $key
     * @return mixed
     */
    public function offsetGet($key)
    {
        return $this->get($key);
    }

    /**
     * Set a configuration option.
     *
     * @param  string  $key
     * @param  mixed   $value
     * @return void
     */
    public function offsetSet($key, $value)
    {
        $this->set($key, $value);
    }

    /**
     * Unset a configuration option.
     *
     * @param  string  $key
     * @return void
     */
    public function offsetUnset($key)
    {
        $this->delete($key);
    }
}
