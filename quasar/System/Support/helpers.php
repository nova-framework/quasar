<?php

use Quasar\System\Debug\Dumper;


function is_member(array $members, $userId)
{
    return ! empty(array_filter($members, function ($member) use ($userId)
    {
        return $member['userId'] === $userId;
    }));
}

/**
 * Get an item from an array using "dot" notation.
 *
 * @param  array   $array
 * @param  string  $key
 * @param  mixed   $default
 * @return mixed
 */
function array_get($array, $key, $default = null)
{
    if (is_null($key)) {
        return $array;
    } else if (isset($array[$key])) {
        return $array[$key];
    }

    foreach (explode('.', $key) as $segment) {
        if (! is_array($array) || ! array_key_exists($segment, $array)) {
            return $default;
        }

        $array = $array[$segment];
    }

    return $array;
}

/**
 * Check if an item exists in an array using "dot" notation.
 *
 * @param  array   $array
 * @param  string  $key
 * @return bool
 */
function array_has($array, $key)
{
    if (empty($array) || is_null($key)) {
        return false;
    } else if (array_key_exists($key, $array)) {
        return true;
    }

    foreach (explode('.', $key) as $segment) {
        if (! is_array($array) || ! array_key_exists($segment, $array)) {
            return false;
        }

        $array = $array[$segment];
    }

    return true;
}

/**
 * Set an array item to a given value using "dot" notation.
 *
 * If no key is given to the method, the entire array will be replaced.
 *
 * @param  array   $array
 * @param  string  $key
 * @param  mixed   $value
 * @return array
 */
function array_set(&$array, $key, $value)
{
    if (is_null($key)) {
        return $array = $value;
    }

    $keys = explode('.', $key);

    while (count($keys) > 1) {
        $key = array_shift($keys);

        if (! isset($array[$key]) || ! is_array($array[$key])) {
            $array[$key] = array();
        }

        $array =& $array[$key];
    }

    $key = array_shift($keys);

    $array[$key] = $value;

    return $array;
}

/**
 * Dump the passed variables and end the script.
 *
 * @param  mixed
 * @return void
 */
function dd()
{
    array_map(function ($value)
    {
        with(new Dumper)->dump($value);

    }, func_get_args());

    exit(1);
}
