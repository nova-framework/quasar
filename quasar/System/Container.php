<?php

namespace Quasar\System;

use Closure;
use Exception;
use ReflectionClass;


class Container
{
    /**
     * The registered dependencies.
     *
     * @var array
     */
    public static $bindings = array();

    /**
     * The resolved shared instances.
     *
     * @var array
     */
    public static $instances = array();


    /**
     * Register an object and its resolver.
     *
     * @param  string   $name
     * @param  mixed    $resolver
     * @param  bool     $shared
     * @return void
     */
    public static function bind($name, $resolver = null, $shared = false)
    {
        if (is_null($resolver)) {
            $resolver = $name;
        }

        static::$bindings[$name] = compact('resolver', 'shared');
    }

    /**
     * Determine if an object has been registered in the container.
     *
     * @param  string  $name
     * @return bool
     */
    public static function bound($name)
    {
        return array_key_exists($name, static::$bindings);
    }

    /**
     * Register an object as a singleton.
     *
     * Singletons will only be instantiated the first time they are resolved.
     *
     * @param  string   $name
     * @param  Closure  $resolver
     * @return void
     */
    public static function singleton($name, $resolver = null)
    {
        static::bind($name, $resolver, true);
    }

    /**
     * Register an existing instance as a singleton.
     *
     * <code>
     *        // Register an instance as a singletor in the container
     *        Container::instance('mailer', new Mailer);
     * </code>
     *
     * @param  string  $name
     * @param  mixed   $instance
     * @return void
     */
    public static function instance($name, $instance)
    {
        static::$instances[$name] = $instance;
    }

    /**
     * Resolve a given type to an instance.
     *
     * <code>
     *        // Get an instance of the "mailer" object registered in the container
     *        $mailer = Container::make('mailer');
     *
     *        // Get an instance of the "mailer" object and pass parameters to the resolver
     *        $mailer = Container::make('mailer', array('test'));
     * </code>
     *
     * @param  string  $type
     * @param  array   $parameters
     * @return mixed
     */
    public static function make($type, $parameters = array())
    {
        if (isset(static::$instances[$type])) {
            return static::$instances[$type];
        }

        if (! isset(static::$bindings[$type])) {
            $concrete = $type;
        } else {
            $concrete = array_get(static::$bindings[$type], 'resolver', $type);
        }

        if (($concrete == $type) || ($concrete instanceof Closure)) {
            $object = static::build($concrete, $parameters);
        } else {
            $object = static::make($concrete);
        }

        if (isset(static::$bindings[$type]['shared']) && (static::$bindings[$type]['shared'] === true)) {
            static::$instances[$type] = $object;
        }

        Event::fire('quasar.resolving', array($type, $object));

        return $object;
    }

    /**
     * Instantiate an instance of the given type.
     *
     * @param  string  $type
     * @param  array   $parameters
     * @return mixed
     */
    protected static function build($type, $parameters = array())
    {
        if ($type instanceof Closure) {
            return call_user_func_array($type, $parameters);
        }

        $reflector = new ReflectionClass($type);

        if ( ! $reflector->isInstantiable()) {
            throw new Exception("Resolution target [$type] is not instantiable.");
        }

        $constructor = $reflector->getConstructor();

        if (is_null($constructor)) {
            return new $type;
        }

        $dependencies = static::getDependencies($constructor->getParameters(), $parameters);

        return $reflector->newInstanceArgs($dependencies);
    }

    /**
     * Resolve all of the dependencies from the ReflectionParameters.
     *
     * @param  array  $parameters
     * @param  array  $arguments that might have been passed into our resolve
     * @return array
     */
    protected static function getDependencies($parameters, $arguments)
    {
        $dependencies = array();

        foreach ($parameters as $parameter) {
            $dependency = $parameter->getClass();

            if (count($arguments) > 0) {
                $dependencies[] = array_shift($arguments);
            } else if (is_null($dependency)) {
                $dependency[] = static::resolveNonClass($parameter);
            } else {
                $dependencies[] = static::resolve($dependency->name);
            }
        }

        return (array) $dependencies;
    }

    /**
     * Resolves optional parameters for our dependency injection
     * pretty much took backport straight from L4's Illuminate\Container
     *
     * @param ReflectionParameter
     * @return default value
     */
    protected static function resolveNonClass($parameter)
    {
        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        } else {
            throw new \Exception("Unresolvable dependency resolving [$parameter].");
        }
    }

}
