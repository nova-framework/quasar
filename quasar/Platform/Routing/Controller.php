<?php

namespace Quasar\Platform\Routing;

use BadMethodCallException;


class Controller
{
    /**
     * The middleware registered on the controller.
     *
     * @var array
     */
    protected $middleware = array();


    public function callAction($method, array $parameters)
    {
        return call_user_func_array(array($this, $method), $parameters);
    }

    public function middleware($middleware, array $options = array())
    {
        $this->middleware[$middleware] = $options;
    }

    public function gatherMiddleware()
    {
        $result = array();

        foreach ($this->middleware as $middleware => $options) {
            if (isset($options['only']) && ! in_array($method, (array) $options['only'])) {
                continue;
            } else if (! empty($options['except']) && in_array($method, (array) $options['except'])) {
                continue;
            }

            $result[] = $middleware;
        }

        return $result;
    }

    public function __call($method, $parameters)
    {
        throw new BadMethodCallException("Method [$method] does not exist.");
    }
}
