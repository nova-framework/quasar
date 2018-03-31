<?php

namespace Quasar\Platform;

use ArrayAccess;
use Closure;
use Exception;
use ReflectionClass;


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
     * @param  string   $name
     * @param  mixed    $resolver
     * @param  bool     $shared
     * @return void
     */
    public function bind($name, $resolver = null, $shared = false)
    {
        if (is_array($name)) {
            list ($name, $alias) = $name;

            $this->alias($name, $alias);
        } else {
            unset($this->aliases[$name]);
        }

        if (is_null($resolver)) {
            $resolver = $name;
        }

        $this->bindings[$name] = compact('resolver', 'shared');
    }

    /**
     * Determine if an object has been registered in the container.
     *
     * @param  string  $name
     * @return bool
     */
    public function bound($name)
    {
        $type = $this->getAlias($name);

        return array_key_exists($type, $this->bindings);
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
    public function singleton($name, $resolver = null)
    {
        $this->bind($name, $resolver, true);
    }

    /**
     * Register an existing instance as a singleton.
     *
     * @param  string  $name
     * @param  mixed   $instance
     * @return void
     */
    public function instance($name, $instance)
    {
        if (is_array($name)) {
            list ($name, $alias) = $name;

            $this->alias($name, $alias);
        } else {
            unset($this->aliases[$name]);
        }

        $this->instances[$name] = $instance;
    }

    /**
     * Alias a type to a shorter name.
     *
     * @param  string  $type
     * @param  string  $alias
     * @return void
     */
    public function alias($type, $alias)
    {
        $this->aliases[$alias] = $type;
    }

    /**
     * Resolve a given type to an instance.
     *
     * @param  string  $type
     * @param  array   $parameters
     * @return mixed
     */
    public function make($type, $parameters = array())
    {
        $type = $this->getAlias($type);

        if (isset($this->instances[$type])) {
            return $this->instances[$type];
        }

        if (! isset($this->bindings[$type])) {
            $concrete = $type;
        } else {
            $concrete = array_get($this->bindings[$type], 'resolver', $type);
        }

        if (($concrete == $type) || ($concrete instanceof Closure)) {
            $object = $this->build($concrete, $parameters);
        } else {
            $object = $this->make($concrete);
        }

        if (isset($this->bindings[$type]['shared']) && ($this->bindings[$type]['shared'] === true)) {
            $this->instances[$type] = $object;
        }

        return $object;
    }

    /**
     * Instantiate an instance of the given type.
     *
     * @param  string  $type
     * @param  array   $parameters
     * @return mixed
     */
    protected function build($type, $parameters = array())
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

        $dependencies = $this->getDependencies($constructor->getParameters(), $parameters);

        return $reflector->newInstanceArgs($dependencies);
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

            if (count($arguments) > 0) {
                $dependencies[] = array_shift($arguments);
            }

            // No arguments given.
            else if (is_null($dependency)) {
                $dependency[] = $this->resolveNonClass($parameter);
            } else {
                $dependencies[] = $this->make($dependency->name);
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

        throw new \Exception("Unresolvable dependency resolving [$parameter].");
    }

    /**
     * Get the alias for an abstract if available.
     *
     * @param  string  $type
     * @return string
     */
    protected function getAlias($type)
    {
        return isset($this->aliases[$type]) ? $this->aliases[$type] : $type;
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
