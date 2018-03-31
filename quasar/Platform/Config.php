<?php

namespace Quasar\Platform;


class Config
{
    /**
     * @var array
     */
    protected $options = array();


    /**
     * Return true if the key exists.
     * @param string $key
     * @return bool
     */
    public function has($key)
    {
        return array_has($this->options, $key);
    }

    /**
     * Get the value.
     * @param string $key
     * @return mixed|null
     */
    public function get($key, $default = null)
    {
        return array_get($this->options, $key, $default);
    }

    /**
     * Set the value.
     * @param string $key
     * @param mixed $value
     */
    public function set($key, $value)
    {
        array_set($this->options, $key, $value);
    }
}
