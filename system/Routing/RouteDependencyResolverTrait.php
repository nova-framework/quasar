<?php

namespace Quasar\Routing;

use ReflectionFunctionAbstract;


trait RouteDependencyResolverTrait
{
    protected function resolveMethodDependencies(array $parameters, ReflectionFunctionAbstract $reflector)
    {
        $instanceCount = 0;

        $values = array_values($parameters);

        foreach ($reflector->getParameters() as $key => $parameter) {
            if (! is_null($class = $parameter->getClass())) {
                $instance = $this->container->make($class->getName());

                $instanceCount++;

                $this->spliceIntoParameters($parameters, $key, $instance);
            }

            //
            else if (! isset($values[$key - $instanceCount]) && $parameter->isDefaultValueAvailable()) {
                $this->spliceIntoParameters($parameters, $key, $parameter->getDefaultValue());
            }
        }

        return array_values($parameters);
    }

    protected function spliceIntoParameters(array &$parameters, $offset, $value)
    {
        array_splice($parameters, $offset, 0, array($value));
    }
}
