<?php

namespace Quasar\Platform\View;

use BadMethodCallException;


class Factory
{
    /**
     * @var array Array of shared data.
     */
    protected $shared = array();


    /**
     * Returns true if the specified View exists.
     *
     * @param mixed $view
     *
     * @return bool
     */
    public function exists($view)
    {
        $path = $this->getViewPath($view);

        return is_readable($path);
    }

    /**
     * Get a View instance.
     *
     * @param mixed $view
     * @param array $data
     *
     * @return \System\View\View
     * @throws \BadMethodCallException
     */
    public function make($view, $data = array())
    {
        if (! is_readable($path = $this->getViewPath($view))) {
            throw new BadMethodCallException("File path [$path] does not exist");
        }
        return new View($this, $path, $data);
    }

    /**
     * Add a key / value pair to the shared view data.
     *
     * @param  string  $key
     * @param  mixed   $value
     * @return void
     */
    public function share($key, $value = null)
    {
        return $this->shared[$key] = $value;
    }

    /**
     * Get the view path.
     *
     * @return array
     */
    protected function getViewPath($view)
    {
        return QUASAR_PATH .str_replace('/', DS, "Views/${view}.php");
    }

    /**
     * Get all of the shared data for the Factory.
     *
     * @return array
     */
    public function getShared()
    {
        return $this->shared;
    }
}

