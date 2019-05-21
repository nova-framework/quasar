<?php

namespace Quasar\Routing;

use Quasar\Exceptions\FatalThrowableError;
use Quasar\Http\Exceptions\NotFoundHttpException;
use Quasar\Http\Request;
use Quasar\Http\Response;
use Quasar\Routing\Controller;
use Quasar\Container;
use Quasar\Pipeline;

use BadMethodCallException;
use Closure;
use DomainException;
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
     * @var \Quasar\Container
     */
    protected $container;


    public function __construct(Container $container)
    {
        $this->container = $container;

        //
        $config = $container->make('config');

        $this->middleware = $config->get('server.routeMiddleware', array());

        $this->middlewareGroups = $config->get('server.middlewareGroups', array());
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

    protected static function mergeGroup($new, $old)
    {
        if (isset($new['namespace']) && isset($old['namespace'])) {
            $new['namespace'] = trim($old['namespace'], '\\') .'\\' .trim($new['namespace'], '\\');
        } else if (isset($new['namespace'])) {
            $new['namespace'] = trim($new['namespace'], '\\');
        } else if (isset($old['namespace'])) {
            $new['namespace'] = $old['namespace'];
        }

        if (isset($new['prefix']) && isset($old['prefix'])) {
            $new['prefix'] = trim($old['prefix'], '/') .'/' .trim($new['prefix'], '/');
        } else if (isset($old['prefix'])) {
            $new['prefix'] = $old['prefix'];
        }

        $new['where'] = array_merge(
            isset($old['where']) ? $old['where'] : array(),
            isset($new['where']) ? $new['where'] : array()
        );

        return array_merge_recursive(
            array_except($old, array('namespace', 'prefix', 'where')), $new
        );
    }

    public function match(array $methods, $route, $action)
    {
        $methods = array_map('strtoupper', $methods);

        if (is_string($action) || ($action instanceof Closure)) {
            $action = array('uses' => $action);
        }

        // If the action has no 'uses' field, we will look for the inner callable.
        else if (! isset($action['uses'])) {
            $action['uses'] = array_first($action, function ($key, $value)
            {
                return is_callable($value) && is_numeric($key);
            });
        }

        if (! isset($action['uses'])) {
            throw new LogicException("Route [${route}] has no valid callback.");
        } else if (is_string($callback = $action['uses']) && (strpos($callback, '@') === false)) {
            throw new LogicException("Route [${route}] callback must have the form [controller@method].");
        }

        if (is_string($middleware = array_get($action, 'middleware', array()))) {
            $action['middleware'] = explode('|', $middleware);
        }

        if (! empty($this->groupStack)) {
            $group = end($this->groupStack);

            if (is_string($action['uses']) && isset($group['namespace'])) {
                $action['uses'] = $group['namespace'] .'\\' .$action['uses'];
            }

            $action = array_except(
                static::mergeGroup($action, $group), array('namespace', 'prefix')
            );

            if (isset($group['prefix'])) {
                $route = trim($group['prefix'], '/') .'/' .trim($route, '/');
            }
        }

        $route = '/' .trim($route, '/');

        if (in_array('GET', $methods) && ! in_array('HEAD', $methods)) {
            $methods[] = 'HEAD';
        }

        foreach ($methods as $method) {
            if (! array_key_exists($method, $this->routes)) {
                throw new LogicException("Route [${route}] use an invalid HTTP method [${method}].");
            }

            $this->routes[$method][$route] = $action;
        }
    }

    public function handle(Request $request)
    {
        try {
            $response = $this->dispatchWithinStack($request);
        }
        catch (Exception $e) {
            $response = $this->handleException($request, $e);
        }
        catch (Throwable $e) {
            $response = $this->handleException($request, new FatalThrowableError($e));
        }

        if (! $response instanceof Response) {
            $response = new Response($response);
        }

        return $response;
    }

    protected function handleException(Request $request, $e)
    {
        return $this->container['exception']->handleException($request, $e);
    }

    protected function dispatchWithinStack(Request $request)
    {
        $middleware = $this->container['config']->get('server.middleware', array());

        // Create a new Pipeline instance.
        $pipeline = new Pipeline($this->container, $middleware);

        return $pipeline->handle($request, function ($request)
        {
            $response = $this->dispatch($request);

            if (! $response instanceof Response) {
                $response = new Response($response);
            }

            return $response;
        });
    }

    protected function dispatch(Request $request)
    {
        $path = '/' .trim($request->path(), '/');

        // Gather the routes registered for the current HTTP method.
        $routes = array_get($this->routes, $request->method(), array());

        if (! is_null($action = array_get($routes, $route = rawurldecode($path)))) {
            $action['route'] = $route;

            return $this->runActionWithinStack($action, $request);
        }

        foreach ($routes as $route => $action) {
            $pattern = $this->compileRoute(
                $route, array_get($action, 'where', array())
            );

            if (preg_match($pattern, $path, $matches) === 1) {
                $action['route'] = $route;

                $parameters = array_filter($matches, function ($value, $key)
                {
                    return is_string($key) && ! empty($value);

                }, ARRAY_FILTER_USE_BOTH);

                return $this->runActionWithinStack($action, $request, $parameters);
            }
        }

        throw new NotFoundHttpException('Page not found');
    }

    protected function compileRoute($route, array $patterns)
    {
        $optionals = 0;

        $variables = array();

        //
        $patterns = array_merge($this->patterns, $patterns);

        $regexp = preg_replace_callback('#/\{(.*?)(\?)?\}#', function ($matches) use ($route, $patterns, &$optionals, &$variables)
        {
            @list(, $name, $optional) = $matches;

            if (preg_match('/^\d/', $name) === 1) {
                throw new DomainException("Variable name [${name}] cannot start with a digit in route pattern [${route}].");
            } else if (in_array($name, $variables)) {
                throw new LogicException("Route pattern [${route}] cannot reference variable name [${name}] more than once.");
            } else if (strlen($name) > 32) {
                throw new DomainException("Variable name [${name}] cannot be longer than 32 characters in route pattern [${route}].");
            }

            $pattern = array_get($patterns, $name, '[^/]+');

            array_push($variables, $name);

            if ($optional) {
                $optionals++;

                return sprintf('(?:/(?P<%s>%s)', $name, $pattern);
            } else if ($optionals > 0) {
                throw new LogicException("Route pattern [${route}] cannot reference variable [${name}] after one or more optionals.");
            }

            return sprintf('/(?P<%s>%s)', $name, $pattern);

        }, $route);

        return '#^' .$regexp .str_repeat(')?', $optionals) .'$#s';
    }

    protected function runActionWithinStack(array $action, Request $request, array $parameters = array())
    {
        $request->action = $action;

        //
        $instance = null;

        if (is_string($callback = $action['uses'])) {
            list ($controller, $method) = explode('@', $callback);

            if (! class_exists($controller)) {
                throw new LogicException("Controller [${controller}] not found.");
            }

            // Create a Controller instance and check if the method exists.
            else if (! method_exists($instance = $this->container->make($controller), $method)) {
                throw new LogicException("Controller [${controller}] has no method [${method}].");
            }

            $callback = compact('instance', 'method');
        }

        // The action does not reference a Controller.
        else if (! $callback instanceof Closure) {
            throw new LogicException("The callback must be a Closure or a string.");
        }

        $middleware = $this->gatherMiddleware($action, $instance);

        // Create a new Pipeline instance.
        $pipeline = new Pipeline($this->container, $middleware);

        return $pipeline->handle($request, function ($request) use ($callback, $parameters)
        {
            array_unshift($parameters, $request);

            $response = $this->callActionCallback($callback, $parameters);

            if (! $response instanceof Response) {
                $response = new Response($response);
            }

            return $response;
        });
    }

    protected function callActionCallback($callback, array $parameters)
    {
        if ($callback instanceof Closure) {
            return call_user_func_array($callback, $parameters);
        }

        extract($callback);

        return $instance->callAction($method, $parameters);
    }

    public function gatherMiddleware(array $action, Controller $controller = null)
    {
        $middleware = array_get($action, 'middleware', array());

        if (! is_null($controller)) {
            $middleware = array_merge($middleware, $controller->gatherMiddleware());
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
        list ($name, $parameters) = array_pad(explode(':', $name, 2), 2, null);

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
        $results = array();

        foreach ($this->middlewareGroups[$name] as $middleware) {
            if (! isset($this->middlewareGroups[$middleware])) {
                $results[] = $this->parseMiddleware($middleware);

                continue;
            }

            // The middleware refer a middleware group.
            $results = array_merge(
                $results, $this->parseMiddlewareGroup($middleware)
            );
        }

        return $results;
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

            return call_user_func_array(array($this, 'match'), $parameters);
        }

        throw new BadMethodCallException("Method [${method}] does not exist.");
    }
}
