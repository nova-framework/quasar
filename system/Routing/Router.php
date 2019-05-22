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
use Exception;
use LogicException;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
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
        $namespace = array_get($old, 'namespace');

        if (isset($new['namespace'])) {
            $namespace = trim($namespace, '\\') .'\\' .trim($new['namespace'], '\\');
        }

        $new['namespace'] = $namespace;

        //
        $prefix = array_get($old, 'prefix');

        if (isset($new['prefix'])) {
            $prefix = trim($prefix, '/') .'/' .trim($new['prefix'], '/');
        }

        $new['prefix'] = $prefix;

        $new['where'] = array_merge(
            array_get($old, 'where', array()),
            array_get($new, 'where', array())
        );

        return array_merge_recursive(
            array_except($old, array('namespace', 'prefix', 'where')), $new
        );
    }

    public function match($methods, $route, $action)
    {
        if (is_callable($action) || is_string($action)) {
            $action = array('uses' => $action);
        }

        $group = ! empty($this->groupStack) ? last($this->groupStack) : array();

        if (isset($action['uses']) && is_string($action['uses'])) {
            $uses = $action['uses'];

            if (isset($group['namespace'])) {
                $action['uses'] = $uses = $group['namespace'] .'\\' .$uses;
            }

            $action['controller'] = $uses;
        }

        // If the action has no 'uses' field, we will look for the inner callable.
        else if (! isset($action['uses'])) {
            $action['uses'] = $this->findActionClosure($action);
        }

        if (is_string($middleware = array_get($action, 'middleware', array()))) {
            $action['middleware'] = explode('|', $middleware);
        }

        $action = static::mergeGroup($action, $group);

        if (isset($action['prefix'])) {
            $route = trim($action['prefix'], '/') .'/' .trim($route, '/');
        }

        $action['path'] = $route = '/' .trim($route, '/');

        //
        $methods = array_map('strtoupper', (array) $methods);

        if (in_array('GET', $methods) && ! in_array('HEAD', $methods)) {
            $methods[] = 'HEAD';
        }

        foreach ($methods as $method) {
            $this->routes[$method][$route] = $action;
        }
    }

    protected function findActionClosure(array $action)
    {
        return array_first($action, function ($key, $value)
        {
            return is_callable($value) && is_numeric($key);
        });
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

        return $this->prepareResponse($request, $response);
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
            return $this->prepareResponse($request, $this->dispatch($request));
        });
    }

    protected function dispatch(Request $request)
    {
        $path = '/' .trim($request->path(), '/');

        // Gather the routes registered for the current HTTP method.
        $routes = array_get($this->routes, $request->method(), array());

        if (! is_null($action = array_get($routes, $route = rawurldecode($path)))) {
            return $this->runActionWithinStack($action, $request);
        }

        foreach ($routes as $route => $action) {
            $pattern = $this->compileRoutePattern($route, $action);

            if (preg_match($pattern, $path, $matches) !== 1) {
                continue;
            }

            $parameters = array_filter($matches, function ($value, $key)
            {
                return is_string($key) && ! empty($value);

            }, ARRAY_FILTER_USE_BOTH);

            return $this->runActionWithinStack($action, $request, $parameters);
        }

        throw new NotFoundHttpException('Page not found');
    }

    protected function compileRoutePattern($route, array $action)
    {
        $patterns = array_merge(
            $this->patterns, array_get($action, 'where', array())
        );

        return with(new RouteCompiler($route, $patterns))->compile();
    }

    protected function runActionWithinStack(array $action, Request $request, array $parameters = array())
    {
        $action['parameters'] = $parameters;

        $request->route = $action;

        // Gather the route middleware.
        $middleware = $this->gatherMiddleware(
            $action, $callback = $this->resolveActionCallback($action)
        );

        // Create a new Pipeline instance.
        $pipeline = new Pipeline($this->container, $middleware);

        return $pipeline->handle($request, function ($request) use ($callback, $parameters)
        {
            $response = $this->callActionCallback($callback, $parameters);

            return $this->prepareResponse($request, $response);
        });
    }

    protected function resolveActionCallback(array $action)
    {
        $callback = array_get($action, 'uses');

        if ($callback instanceof Closure) {
            return $callback;
        }

        //
        else if (! is_string($callback)) {
            throw new LogicException("The route callback must be a Closure instance or a string.");
        }

        list ($className, $method) = array_pad(explode('@', $callback, 2), 2, null);

        if (is_null($method) || ! class_exists($className)) {
            throw new LogicException("Invalid route action: [{$callback}]");
        }

        //
        else if (! method_exists($controller = $this->container->make($className), $method)) {
            throw new LogicException("Controller [${className}] has no method [${method}].");
        }

        return compact('controller', 'method');
    }

    protected function callActionCallback($callback, array $parameters)
    {
        if ($callback instanceof Closure) {
            return call_user_func_array($callback, $this->resolveCallParameters(
                $parameters, new ReflectionFunction($callback)
            ));
        }

        extract($callback);

        return $controller->callAction($method, $this->resolveCallParameters(
            $parameters, new ReflectionMethod($controller, $method)
        ));
    }

    protected function resolveCallParameters(array $parameters, ReflectionFunctionAbstract $reflector)
    {
        $instanceCount = 0;

        $values = array_values($parameters);

        foreach ($reflector->getParameters() as $key => $parameter) {
            if (! is_null($class = $parameter->getClass())) {
                $instance = $this->container->make($class->getName());

                $instanceCount++;

                $this->spliceIntoParameters($parameters, $key, $instance);
            }

            // The parameter does not references a class.
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

    public function gatherMiddleware(array $action, $callback)
    {
        $middleware = array_get($action, 'middleware', array());

        if (is_array($callback)) {
            extract($callback);

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

    public function middleware($name, $middleware)
    {
        $this->middleware[$name] = $middleware;

        return $this;
    }

    public function pattern($key, $pattern)
    {
        $this->patterns[$key] = $pattern;
    }

    public function prepareResponse($request, $response)
    {
        if (! $response instanceof Response) {
            return new Response($response);
        }

        return $response;
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
