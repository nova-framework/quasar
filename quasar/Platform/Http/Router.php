<?php

namespace Quasar\Platform\Http;

use Quasar\Platform\Exceptions\FatalThrowableError;
use Quasar\Platform\Exceptions\Handler;
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


    public function __construct($path, array $middlewareGroups = array(), array $middleware = array())
    {
        $this->middleware = $middleware;

        $this->middlewareGroups = $middlewareGroups;

        //
        $this->loadRoutes($path);
    }

    public function any($route, $action)
    {
        $methods = array('GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'HEAD');

        return $this->match($methods, $route, $action);
    }

    public function group(array $attributes, Closure $callback)
    {
        if (isset($attributes['middleware']) && is_string($attributes['middleware'])) {
            $attributes['middleware'] = explode('|', $attributes['middleware']);
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
            $namespace = array_get($old, 'namespace');
        } else if (isset($old['namespace']))  {
            $namespace = trim($old['namespace'], '\\') .'\\' .trim($new['namespace'], '\\');
        } else {
            $namespace = trim($new['namespace'], '\\');
        }

        $new['namespace'] = $namespace;

        //
        $prefix = trim(array_get($old, 'prefix'), '/');

        if (isset($new['prefix'])) {
            $new['prefix'] = $prefix .'/' .trim($new['prefix'], '/');
        } else {
            $new['prefix'] = $prefix;
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

        if (in_array('GET', $methods) && ! in_array('HEAD', $methods)) {
            $methods[] = 'HEAD';
        }

        $group = ! empty($this->groupStack) ? end($this->groupStack) : array();

        if (is_callable($action)) {
            $action = array('uses' => $action);
        }

        // If the action references a Controller.
        else if (is_string($action) || (isset($action['uses']) && is_string($action['uses']))) {
            if (is_string($action)) {
                $action = array('uses' => $action);
            }

            if (! empty($group)) {
                $uses = $action['uses'];

                $action['uses'] = isset($group['namespace']) ? $group['namespace'] .'\\' .$uses : $uses;
            }

            $action['controller'] = $action['uses'];
        }

        // If the action hasn't a proper 'uses'
        else if (! isset($action['uses'])) {
            $action['uses'] = $this->findActionClosure($action);
        }

        if (isset($action['middleware']) && is_string($action['middleware'])) {
            $action['middleware'] = explode('|', $action['middleware']);
        }

        if (! empty($group)) {
            $action = static::mergeGroup($action, $group);
        }

        $prefix = isset($action['prefix']) ? $action['prefix'] : '';

        $route = trim(trim($prefix, '/') .'/' .trim($route, '/'), '/') ?: '/';

        foreach ($methods as $method) {
            if (array_key_exists($method, $this->routes)) {
                $this->routes[$method][$route] = $action;
            }
        }
    }

    public function dispatch(Request $request = null)
    {
        if (is_null($request)) {
            $request = Request::createFromGlobals();
        }

        try {
            $response = $this->matchRoutes($request);
        }
        catch (Exception $e) {
            $response = $this->handleException($request, $e);
        }
        catch (Throwable $e) {
            $response = $this->handleException($request, new FatalThrowableError($e));
        }

        return $response;
    }

    protected function handleException(Request $request, $e)
    {
        $handler = Container::make(Handler::class);

        return $handler->handleException($e);
    }

    protected function matchRoutes(Request $request)
    {
        $method = $request->method();

        $path = $request->path();

        // Get the routes by HTTP method.
        $routes = isset($this->routes[$method]) ? $this->routes[$method] : array();

        foreach ($routes as $route => $action) {
            $wheres = isset($action['where']) ? $action['where'] : array();

            $pattern = $this->compileRoute($route, array_merge($this->patterns, $wheres));

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

    protected function compileRoute($route, array $patterns)
    {
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
        $callback = $action['uses'];

        // Add the action to Request instance.
        $request->action = $action;

        // Gather the middleware and create a Pipeline instance.
        $pipeline = new Pipeline($this->gatherMiddleware($action), 'handle');

        return $pipeline->handle($request, function ($request) use ($callback, $parameters)
        {
            // The Request instance should be always the callback's first parameter.
            array_unshift($parameters, $request);

            $response = $this->call($callback, $parameters);

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
        else if (! method_exists($instance = Container::make($controller), $method)) {
            throw new LogicException("Controller [$controller] has no method [$method].");
        }

        return $instance->callAction($method, $parameters);
    }

    public function gatherMiddleware(array $action)
    {
        $middleware = isset($action['middleware']) ? $action['middleware'] : array();

        if (! empty($controller = array_get($action, 'controller'))) {
            list ($name, $method) = explode('@', $controller);

            $controller = Container::make($name);

            $middleware = array_merge($middleware, $controller->gatherMiddleware());
        }

        return array_map(function ($name)
        {
            if (isset($this->middlewareGroups[$name])) {
                return $this->parseMiddlewareGroup($name);
            }

            return $this->parseMiddleware($name);

        }, array_unique($middleware, SORT_REGULAR));
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

    public function loadRoutes($path)
    {
        $router = $this;

        if (is_readable($path = str_replace('/', DS, $path))) {
            require $path;
        }
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
