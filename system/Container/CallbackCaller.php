<?php

namespace Quasar\Container;

use Quasar\Container;

use Closure;
use InvalidArgumentException;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;


class CallbackCaller
{
    /**
     * Call the given Closure / class@method and inject its dependencies.
     *
     * @param  \Quasar\Container  $container
     * @param  callable|string  $callback
     * @param  array  $parameters
     * @param  string|null  $defaultMethod
     * @return mixed
     */
    public static function call(Container $container, $callback, array $parameters = array(), $defaultMethod = null)
    {
        if (is_string($callback)) {
            $callback = static::resolveStringCallback($container, $callback, $defaultMethod);
        }

        if ($callback instanceof Closure) {
            $reflector = new ReflectionFunction($callback);
        }

        //
        else if (is_array($callback)) {
            list ($instance, $method) = array_pad($callback, 2, $defaultMethod);

            $reflector = new ReflectionMethod($instance, $method);
        } else {
            throw new InvalidArgumentException('Invalid callback provided.');
        }

        return call_user_func_array(
            $callback, static::getMethodDependencies($container, $parameters, $reflector)
        );
    }

    /**
     * Resolve the given string callback.
     *
     * @param  \Quasar\Container  $container
     * @param  callable|string  $callback
     * @param  mixed  $defaultMethod
     * @return array
     */
    protected static function resolveStringCallback(Container $container, $callback, $defaultMethod)
    {
        list ($className, $method) = array_pad(explode('@', $callback, 2), 2, $defaultMethod);

        if (empty($method) || ! class_exists($className)) {
            throw new InvalidArgumentException('Invalid callback provided.');
        }

        return array($container->make($className), $method);
    }

    /**
     * Get all dependencies for a given method.
     *
     * @param  \Quasar\Container  $container
     * @param  array  $parameters
     * @param  \ReflectionFunctionAbstract  $reflector
     * @return array
     */
    protected static function getMethodDependencies(Container $container, array $parameters, ReflectionFunctionAbstract $reflector)
    {
        $dependencies = array();

        foreach ($reflector->getParameters() as $parameter) {
            if (array_key_exists($name = $parameter->name, $parameters)) {
                $dependencies[] = $parameters[$name];

                unset($parameters[$name]);
            }

            // The dependency does not exists in parameters.
            else if (! is_null($class = $parameter->getClass())) {
                $dependencies[] = $container->make($class->name);
            }

            // The dependency does not reference a class.
            else if ($parameter->isDefaultValueAvailable()) {
                $dependencies[] = $parameter->getDefaultValue();
            }
        }

        return array_merge($dependencies, $parameters);
    }
}
