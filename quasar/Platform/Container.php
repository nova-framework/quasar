<?php

namespace Quasar\Platform;

use ArrayAccess;
use Closure;
use Exception;
use ReflectionClass;


class BindingResolutionException extends Exception {};

class Container implements ArrayAccess
{
    /**
     * The current globally available container (if any).
     *
     * @var static
     */
    protected static $instance;

    /**
     * The registered dependencies.
     *
     * @var array
     */
    protected $bindings = array();

    /**
     * The resolved shared instances.
     *
     * @var array
     */
    protected $instances = array();

    /**
     * The registered type aliases.
     *
     * @var array
     */
    protected $aliases = array();


    /**
     * Register an object and its resolver.
     *
     * @param  string   $abstract
     * @param  mixed    $concrete
     * @param  bool     $shared
     * @return void
     */
    public function bind($abstract, $concrete = null, $shared = false)
    {
        if (is_array($abstract)) {
            list ($abstract, $alias) = $abstract;

            $this->alias($abstract, $alias);
        }

        unset($this->instances[$abstract], $this->aliases[$abstract]);

        if (is_null($concrete)) {
            $concrete = $abstract;
        }

        if (! $concrete instanceof Closure) {
            $concrete = $this->getClosure($abstract, $concrete);
        }

        $this->bindings[$abstract] = compact('concrete', 'shared');
    }

    /**
     * Get the Closure to be used when building a type.
     *
     * @param  string  $abstract
     * @param  string  $concrete
     * @return \Closure
     */
    protected function getClosure($abstract, $concrete)
    {
        return function($container, $parameters = array()) use ($abstract, $concrete)
        {
            $method = ($abstract == $concrete) ? 'build' : 'make';

            return call_user_func(array($container, $method), $concrete, $parameters);
        };
    }

    /**
     * Determine if an object has been registered in the container.
     *
     * @param  string  $abstract
     * @return bool
     */
    public function bound($abstract)
    {
        $abstract = $this->getAlias($abstract);

        return array_key_exists($abstract, $this->bindings);
    }

    /**
     * Register an object as a singleton.
     *
     * Singletons will only be instantiated the first time they are resolved.
     *
     * @param  string   $abstract
     * @param  Closure  $concrete
     * @return void
     */
    public function singleton($abstract, $concrete = null)
    {
        $this->bind($abstract, $concrete, true);
    }

    /**
     * Register an existing instance as a singleton.
     *
     * @param  string  $abstract
     * @param  mixed   $instance
     * @return void
     */
    public function instance($abstract, $instance)
    {
        if (is_array($abstract)) {
            list ($abstract, $alias) = $abstract;

            $this->alias($abstract, $alias);
        }

        unset($this->aliases[$abstract]);

        $this->instances[$abstract] = $instance;
    }

    /**
     * Alias a type to a shorter name.
     *
     * @param  string  $abstract
     * @param  string  $alias
     * @return void
     */
    public function alias($abstract, $alias)
    {
        $this->aliases[$alias] = $abstract;
    }

    /**
     * Resolve a given type to an instance.
     *
     * @param  string  $abstract
     * @param  array   $parameters
     * @return mixed
     */
    public function make($abstract, $parameters = array())
    {
        $abstract = $this->getAlias($abstract);

        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        if (! isset($this->bindings[$abstract])) {
            $concrete = $abstract;
        } else {
            $concrete = array_get($this->bindings[$abstract], 'concrete', $abstract);
        }

        if (($concrete == $abstract) || ($concrete instanceof Closure)) {
            $object = $this->build($concrete, $parameters);
        } else {
            $object = $this->make($concrete);
        }

        if (array_get($this->bindings[$abstract], 'shared', false) === true)) {
            $this->instances[$abstract] = $object;
        }

        return $object;
    }

    /**
     * Instantiate an instance of the given type.
     *
     * @param  string  $abstract
     * @param  array   $parameters
     * @return mixed
     */
    protected function build($abstract, $parameters = array())
    {
        if ($abstract instanceof Closure) {
            return call_user_func($abstract, $this, $parameters);
        }

        $reflector = new ReflectionClass($abstract);

        if ( ! $reflector->isInstantiable()) {
            throw new BindingResolutionException("Resolution target [$abstract] is not instantiable.");
        }

        $constructor = $reflector->getConstructor();

        if (is_null($constructor)) {
            return new $abstract;
        }

        $dependencies = $constructor->getParameters();

        $parameters = $this->getDependencies(
            $dependencies, $this->keyParametersByArgument($dependencies, $parameters)
        );

        return $reflector->newInstanceArgs($parameters);
    }

    /**
     * Resolve all of the dependencies from the ReflectionParameters.
     *
     * @param  array  $parameters
     * @param  array  $arguments that might have been passed into our resolve
     * @return array
     */
    protected function getDependencies($parameters, $arguments)
    {
        $dependencies = array();

        foreach ($parameters as $parameter) {
            $dependency = $parameter->getClass();

            if (array_key_exists($name = $parameter->name, $arguments)) {
                $dependencies[] = $arguments[$name];
            }

            // No arguments given.
            else if (is_null($dependency)) {
                $dependencies[] = $this->resolveNonClass($parameter);
            } else {
                $dependencies[] = $this->resolveClass($parameter);
            }
        }

        return (array) $dependencies;
    }

    /**
     * Resolves optional parameters for our dependency injection
     *
     * @param ReflectionParameter
     * @return default value
     */
    protected function resolveNonClass($parameter)
    {
        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }

        throw new BindingResolutionException("Unresolvable dependency resolving [$parameter].");
    }

    /**
     * Resolve a class based dependency from the container.
     *
     * @param  \ReflectionParameter  $parameter
     * @return mixed
     *
     * @throws BindingResolutionException
     */
    protected function resolveClass($parameter)
    {
        try {
            return $this->make($parameter->getClass()->name);
        }
        catch (BindingResolutionException $e) {
            if ($parameter->isOptional()) {
                return $parameter->getDefaultValue();
            }

            throw $e;
        }
    }

    /**
     * If extra parameters are passed by numeric ID, rekey them by argument name.
     *
     * @param  array  $dependencies
     * @param  array  $parameters
     * @return array
     */
    protected function keyParametersByArgument(array $dependencies, array $parameters)
    {
        foreach ($parameters as $key => $value) {
            if (is_numeric($key)) {
                unset($parameters[$key]);

                $name = $dependencies[$key]->name;

                $parameters[$name] = $value;
            }
        }

        return $parameters;
    }

    /**
     * Get the alias for an abstract if available.
     *
     * @param  string  $abstract
     * @return string
     */
    protected function getAlias($abstract)
    {
        return isset($this->aliases[$abstract]) ? $this->aliases[$abstract] : $abstract;
    }

    /**
     * Set the globally available instance of the container.
     *
     * @return static
     */
    public static function getInstance()
    {
        return static::$instance;
    }

    /**
     * Set the shared instance of the container.
     *
     * @param  \Mini\Container\Container  $container
     * @return void
     */
    public static function setInstance(Container $container)
    {
        static::$instance = $container;
    }

    /**
     * Determine if a given offset exists.
     *
     * @param  string  $key
     * @return bool
     */
    public function offsetExists($key)
    {
        return isset($this->bindings[$key]);
    }

    /**
     * Get the value at a given offset.
     *
     * @param  string  $key
     * @return mixed
     */
    public function offsetGet($key)
    {
        return $this->make($key);
    }

    /**
     * Set the value at a given offset.
     *
     * @param  string  $key
     * @param  mixed   $value
     * @return void
     */
    public function offsetSet($key, $value)
    {
        if (! $value instanceof Closure) {
            $value = function() use ($value)
            {
                return $value;
            };
        }

        $this->bind($key, $value);
    }

    /**
     * Unset the value at a given offset.
     *
     * @param  string  $key
     * @return void
     */
    public function offsetUnset($key)
    {
        unset($this->bindings[$key], $this->instances[$key]);
    }
}
