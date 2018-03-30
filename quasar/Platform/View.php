<?php

namespace Quasar\Platform;

use BadMethodCallException;
use Exception;


class View
{
    /**
     * @var string The path to the View file on disk.
     */
    protected $path = null;

    /**
     * @var array Array of local data.
     */
    protected $data = array();

    /**
     * @var array Array of shared data.
     */
    protected static $shared = array();


    /**
     * Constructor
     * @param mixed $path
     * @param array $data
     *
     * @throws \BadMethodCallException
     */
    protected function __construct($path, $data = array())
    {
        if (! is_readable($path)) {
            throw new BadMethodCallException("File path [$path] does not exist");
        }

        $this->path = $path;

        $this->data = is_array($data) ? $data : array($data);

    }

    /**
     * Returns true if the specified View exists.
     *
     * @param mixed $view
     *
     * @return bool
     */
    public static function exists($view)
    {
        $path = QUASAR_PATH .str_replace('/', DS, "Views/${view}.php");

        return is_readable($path);
    }

    /**
     * Get a View instance.
     *
     * @param mixed $view
     * @param array $data
     *
     * @return \System\View\View
     */
    public static function make($view, $data = array())
    {
        $path = QUASAR_PATH .str_replace('/', DS, "Views/${view}.php");

        return new static($path, $data);
    }

    /**
     * Get the string contents of the View.
     *
     * @param  \Closure  $callback
     * @return string
     */
    public function render()
    {
        $__data = $this->gatherData();

        ob_start();

        // Extract the rendering variables.
        foreach ($__data as $__variable => $__value) {
            ${$__variable} = $__value;
        }

        unset($__variable, $__value);

        try {
            include $this->path;
        }
        catch (\Exception $e) {
            ob_get_clean();

            throw $e;
        }

        return ltrim(ob_get_clean());
    }

    /**
     * Return all variables stored on local and shared data.
     *
     * @return array
     */
    protected function gatherData()
    {
        $data = array_merge(static::$shared, $this->data);

        foreach ($data as $key => $value) {
            if ($value instanceof View) {
                $data[$key] = $value->render();
            }
        }

        return $data;
    }

    /**
     * Add a view instance to the view data.
     *
     * @param  string  $key
     * @param  string  $view
     * @param  array   $data
     * @return View
     */
    public function nest($key, $view, $data = array())
    {
        return $this->with($key, static::make($view, $data));
    }

    /**
     * Add a key / value pair to the shared view data.
     *
     * @param  string  $key
     * @param  mixed   $value
     * @return void
     */
    public static function share($key, $value = null)
    {
        return static::$shared[$key] = $value;
    }

    /**
     * Add a key / value pair to the shared view data.
     *
     * @param  string  $key
     * @param  mixed   $value
     * @return View
     */
    public function shares($key, $value)
    {
        static::share($key, $value);

        return $this;
    }

    /**
     * Add a key / value pair to the view data.
     *
     * Bound data will be available to the view as variables.
     *
     * @param  string  $key
     * @param  mixed   $value
     * @return View
     */
    public function with($key, $value = null)
    {
        if (is_array($key)) {
            $this->data = array_merge($this->data, $key);
        } else {
            $this->data[$key] = $value;
        }

        return $this;
    }

    /**
     * Get the evaluated string content of the View.
     *
     * @return string
     */
    public function __toString()
    {
        try {
            return $this->render();
        }
        catch (Exception $e) {
            return '';
        }
    }

    /**
     * Magic Method for handling dynamic functions.
     *
     * @param  string  $method
     * @param  array   $params
     * @return \System\View\View|static|void
     *
     * @throws \BadMethodCallException
     */
    public function __call($method, $params)
    {
        // Add the support for the dynamic withX Methods.
        if (substr($method, 0, 4) == 'with') {
            $name = lcfirst(substr($method, 4));

            return $this->with($name, array_shift($params));
        }

        throw new BadMethodCallException("Method [$method] does not exist");
    }
}

