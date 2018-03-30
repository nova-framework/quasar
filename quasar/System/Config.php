<?php

namespace Quasar\System;


class Config
{
    /**
     * @var array
     */
    protected static $options = array();


    /**
     * Return true if the key exists.
     * @param string $key
     * @return bool
     */
    public static function has($key)
    {
        return array_has(static::$options, $key);
    }

    /**
     * Get the value.
     * @param string $key
     * @return mixed|null
     */
    public static function get($key, $default = null)
    {
        return array_get(static::$options, $key, $default);
    }

    /**
     * Set the value.
     * @param string $key
     * @param mixed $value
     */
    public static function set($key, $value)
    {
        array_set(static::$options, $key, $value);
    }
}
