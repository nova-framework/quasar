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
        if ($callback instanceof Closure) {
            $reflector = new ReflectionFunction($callback);
        }

        //
        else if (is_string($callback)) {
            list ($className, $method) = array_pad(explode('@', $callback, 2), 2, $defaultMethod);

            if (is_null($method) || ! class_exists($className)) {
                throw new InvalidArgumentException('Invalid callback provided.');
            }

            $callback = array($instance = $container->make($className), $method);

            $reflector = new ReflectionMethod($instance, $method);
        }

        //
        else if (is_array($callback)) {
            $reflector = new ReflectionMethod($callback[0], $callback[1]);
        }

        return call_user_func_array(
            $callback, static::getMethodDependencies($container, $parameters, $reflector)
        );
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
