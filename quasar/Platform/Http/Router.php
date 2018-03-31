<?php

namespace Quasar\Platform\Http;

use Quasar\Platform\Http\Exceptions\NotFoundHttpException;
use Quasar\Platform\Http\Request;
use Quasar\Platform\Http\Response;
use Quasar\Platform\Container;
use Quasar\Platform\Pipeline;

use Closure;
use Exception;
use LogicException;
use Throwable;


class Router
{
    /**
     * An array of registered routes.
     *
     * @var array
     */
    protected $routes = array(
        'GET'     => array(),
        'POST'    => array(),
        'PUT'     => array(),
        'DELETE'  => array(),
        'PATCH'   => array(),
        'HEAD'    => array(),
        'OPTIONS' => array(),
    );

    /**
     * All of the short-hand keys for middlewares.
     *
     * @var array
     */
    protected $middleware = array();

    /**
     * All of the middleware groups.
     *
     * @var array
     */
    protected $middlewareGroups = array();

    /**
     * The route group attribute stack.
     *
     * @var array
     */
    protected $groupStack = array();

    /**
     * The global parameter patterns.
     *
     * @var array
     */
    protected $patterns = array();

    /**
     * The Container instance.
     *
     * @var \Quasar\Platform\Container
     */
    protected $container;


    public function __construct(Container $container)
    {
        $this->container = $container;

        //
        $this->middleware = $container['config']->get('platform.routeMiddleware', array());

        $this->middlewareGroups = $container['config']->get('platform.middlewareGroups', array());
    }

    public function any($route, $action)
    {
        $methods = array('GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'HEAD');

        return $this->match($methods, $route, $action);
    }

    public function group(array $attributes, Closure $callback)
    {
        if (is_string($middleware = array_get($attributes, 'middleware', array()))) {
            $attributes['middleware'] = explode('|', $middleware);
        }

        if (! empty($this->groupStack)) {
            $attributes = static::mergeGroup($attributes, end($this->groupStack));
        }

        $this->groupStack[] = $attributes;

        call_user_func($callback, $this);

        array_pop($this->groupStack);
    }

    public static function mergeGroup($new, $old)
    {
        if (! isset($new['namespace'])) {
            $new['namespace'] = array_get($old, 'namespace');
        } else if (isset($old['namespace']))  {
            $new['namespace'] = trim($old['namespace'], '\\') .'\\' .trim($new['namespace'], '\\');
        }

        $prefix = trim(array_get($old, 'prefix'), '/');

        if (isset($new['prefix'])) {
            $new['prefix'] = $prefix .'/' .trim($new['prefix'], '/');
        } else {
            $new['prefix'] = $prefix;
        }

        $new['where'] = array_merge(array_get($old, 'where', array()), array_get($new, 'where', array()));

        return array_merge_recursive(
            array_except($old, array('namespace', 'prefix', 'where')), $new
        );
    }

    public function match(array $methods, $route, $action)
    {
        $methods = array_map('strtoupper', $methods);

        if (in_array('GET', $methods) && ! in_array('HEAD', $methods)) {
            $methods[] = 'HEAD';
        }

        if (is_string($action) || is_callable($action)) {
            $action = array('uses' => $action);
        }

        // If the action hasn't a proper 'uses' field.
        else if (! isset($action['uses'])) {
            $action['uses'] = $this->findActionClosure($action);
        }

        if (is_string($middleware = array_get($action, 'middleware', array()))) {
            $action['middleware'] = explode('|', $middleware);
        }

        if (! empty($this->groupStack)) {
            $group = end($this->groupStack);

            // When the action references a Controller.
            if (is_string($action['uses']) && ! empty($namespace = array_get($group, 'namespace'))) {
                $action['uses'] = trim($namespace, '\\') .'\\' .$action['uses'];
            }

            $action = static::mergeGroup($action, $group);

            $route = trim(array_get($group, 'prefix'), '/') .'/' .trim($route, '/');
        }

        $route = trim($route, '/') ?: '/';

        foreach ($methods as $method) {
            if (array_key_exists($method, $this->routes)) {
                $this->routes[$method][$route] = $action;
            }
        }
    }

    public function dispatch(Request $request)
    {
        $method = $request->method();

        $path = $request->path();

        // Gather the routes registered for the current HTTP method.
        $routes = array_get($this->routes, $method, array());

        foreach ($routes as $route => $action) {
            $pattern = $this->compileRoute($route, array_get($action, 'where', array()));

            if (preg_match($pattern, $path, $matches) === 1) {
                $action['route'] = $route;

                $parameters = array_filter($matches, function ($value, $key)
                {
                    return is_string($key) && ! empty($value);

                }, ARRAY_FILTER_USE_BOTH);

                return $this->runActionWithinStack($action, $parameters, $request);
            }
        }

        throw new NotFoundHttpException('Page not found');
    }

    protected function compileRoute($route, array $wheres)
    {
        $patterns = array_merge($this->patterns, $wheres);

        //
        $optionals = 0;

        $variables = array();

        $regexp = preg_replace_callback('#/\{(.*?)(\?)?\}#', function ($matches) use ($route, $patterns, &$optionals, &$variables)
        {
            @list(, $name, $optional) = $matches;

            if (in_array($name, $variables)) {
                throw new LogicException("Pattern [$route] cannot reference variable name [$name] more than once.");
            }

            $variables[] = $name;

            $pattern = isset($patterns[$name]) ? $patterns[$name] : '[^/]+';

            if ($optional) {
                $optionals++;

                return sprintf('(?:/(?P<%s>%s)', $name, $pattern);
            } else if ($optionals > 0) {
                throw new LogicException("Pattern [$route] cannot reference variable [$name] after one or more optionals.");
            }

            return sprintf('/(?P<%s>%s)', $name, $pattern);

        }, $route);

        if ($optionals > 0) {
            $regexp .= str_repeat(')?', $optionals);
        }

        return '#^' .$regexp .'$#s';
    }

    protected function runActionWithinStack(array $action, array $parameters, Request $request)
    {
        $request->action = $action;

        // Gather the middleware and create a Pipeline instance.
        $middleware = $this->gatherMiddleware($action);

        $pipeline = new Pipeline($this->container, $middleware);

        return $pipeline->handle($request, function ($request) use ($action, $parameters)
        {
            array_unshift($parameters, $request);

            $response = $this->call($action['uses'], $parameters);

            if (! $response instanceof Response) {
                return new Response($response);
            }

            return $response;
        });
    }

    protected function findActionClosure(array $action)
    {
        foreach ($action as $key => $value) {
            if (is_numeric($key) && ($value instanceof Closure)) {
                return $value;
            }
        }
    }

    protected function call($callback, array $parameters)
    {
        if ($callback instanceof Closure) {
            return call_user_func_array($callback, $parameters);
        }

        list ($controller, $method) = explode('@', $callback);

        if (! class_exists($controller)) {
            throw new LogicException("Controller [$controller] not found.");
        }

        // Create the Controller instance and check the specified method.
        else if (! method_exists($instance = $this->container->make($controller), $method)) {
            throw new LogicException("Controller [$controller] has no method [$method].");
        }

        return $instance->callAction($method, $parameters);
    }

    public function gatherMiddleware(array $action)
    {
        $middleware = array_get($action, 'middleware', array());

        if (is_string($action['uses'])) {
            list ($controller, $method) = explode('@', $action['uses']);

            $instance = $this->container->make($controller);

            $middleware = array_merge($middleware, $instance->gatherMiddleware());
        }

        return array_flatten(array_map(function ($name)
        {
            if (isset($this->middlewareGroups[$name])) {
                return $this->parseMiddlewareGroup($name);
            }

            return $this->parseMiddleware($name);

        }, array_unique($middleware, SORT_REGULAR)));
    }

    protected function parseMiddleware($name)
    {
        list($name, $parameters) = array_pad(explode(':', $name, 2), 2, null);

        //
        $callable = isset($this->middleware[$name]) ? $this->middleware[$name] : $name;

        if (is_null($parameters)) {
            return $callable;
        }

        // The middleware have parameters.
        else if (is_string($callable)) {
            return $callable .':' .$parameters;
        }

        return function ($passable, $stack) use ($callable, $parameters)
        {
            return call_user_func_array(
                $callable, array_merge(array($passable, $stack), explode(',', $parameters))
            );
        };
    }

    protected function parseMiddlewareGroup($name)
    {
        $result = array();

        foreach ($this->middlewareGroups[$name] as $middleware) {
            if (! isset($this->middlewareGroups[$middleware])) {
                $result[] = $this->parseMiddleware($middleware);

                continue;
            }

            // The middleware refer a middleware group.
            $results = array_merge(
                $result, $this->parseMiddlewareGroup($middleware)
            );
        }

        return $result;
    }

    public function middleware($name, $middleware)
    {
        $this->middleware[$name] = $middleware;

        return $this;
    }

    public function pattern($key, $pattern)
    {
        $this->patterns[$key] = $pattern;
    }

    public function __call($method, $parameters)
    {
        if (array_key_exists($key = strtoupper($method), $this->routes)) {
            array_unshift($parameters, array($key));

            $method = 'match';
        }

        return call_user_func_array(array($this, $method), $parameters);
    }
}
