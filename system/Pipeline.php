<?php

namespace Quasar;

use Closure;
use LogicException;


class Pipeline
{
    /**
     * The Container instance.
     *
     * @var \Quasar\Container
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
    public function handle($passable, Closure $callback)
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

        $parameters = array($passable, $stack);

        if (! is_object($pipe)) {
            list ($name, $arguments) = array_pad(explode(':', $pipe, 2), 2, array());

            $pipe = $this->container->make($name);

            $parameters = array_merge(
                $parameters, is_array($arguments) ? $arguments : explode(',', $arguments)
            );
        }

        return call_user_func_array(array($pipe, $this->method), $parameters);
    }
}
