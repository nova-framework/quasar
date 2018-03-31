<?php

namespace Quasar\Platform;

use Quasar\Platform\Container;

use Closure;
use LogicException;


class Pipeline
{
    /**
     * The Container instance.
     *
     * @var \Quasar\Platform\Container
     */
    protected $container;

    /**
     * The array of class pipes.
     *
     * @var array
     */
    protected $pipes = array();

    /**
     * The method to call on each pipe.
     *
     * @var string
     */
    protected $method = 'handle';


    /**
     * Create a new class instance.
     *
     * @param  mixed|array  $pipes
     * @param  string|null  $method
     * @return void
     */
    public function __construct(Container $container, $pipes = array(), $method = null)
    {
        $this->container = $container;

        $this->pipes = is_array($pipes) ? $pipes : array($pipes);

        if (! is_null($method)) {
            $this->method = $method;
        }
    }

    /**
     * Run the pipeline with a final destination callback.
     *
     * @param  mixed  $passable
     * @param  \Closure  $callback
     * @return mixed
     */
    public function dispatch($passable, Closure $callback)
    {
        $pipeline = array_reduce(array_reverse($this->pipes), function ($stack, $pipe)
        {
            return $this->createSlice($stack, $pipe);

        }, $this->prepareDestination($callback));

        return call_user_func($pipeline, $passable);
    }

    /**
     * Get the initial slice to begin the stack call.
     *
     * @param  \Closure  $callback
     * @return \Closure
     */
    protected function prepareDestination(Closure $callback)
    {
        return function ($passable) use ($callback)
        {
            return call_user_func($callback, $passable);
        };
    }

    /**
     * Get a Closure that represents a slice of the application onion.
     *
     * @param  \Closure  $stack
     * @param  mixed  $pipe
     * @return \Closure
     */
    protected function createSlice(Closure $stack, $pipe)
    {
        return function ($passable) use ($stack, $pipe)
        {
            return $this->call($pipe, $passable, $stack);
        };
    }

    /**
     * Call the pipe Closure or the method 'handle' in its class instance.
     *
     * @param  mixed  $pipe
     * @param  mixed  $passable
     * @param  \Closure  $stack
     * @return \Closure
     * @throws \BadMethodCallException
     */
    protected function call($pipe, $passable, Closure $stack)
    {
        if ($pipe instanceof Closure) {
            return call_user_func($pipe, $passable, $stack);
        }

        // The pipe is not a Closure.
        else if (! is_object($pipe)) {
            list($name, $parameters) = array_pad(explode(':', $pipe, 2), 2, array());

            if (is_string($parameters)) {
                $parameters = explode(',', $parameters);
            }

            $pipe = $this->container->make($name);

            $parameters = array_merge(array($passable, $stack), $parameters);
        } else {
            $parameters = array($passable, $stack);
        }

        return call_user_func_array(array($pipe, $this->method), $parameters);
    }
}
