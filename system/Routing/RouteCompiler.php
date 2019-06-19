<?php

namespace Quasar\Routing;

use DomainException;
use LogicException;


class RouteCompiler
{
    /**
     * The route path.
     *
     * @var string
     */
    protected $path;

    /**
     * The route patterns.
     *
     * @var array
     */
    protected $patterns = array();


    /**
     * Create a new Route compiler instance.
     *
     * @param  string  $path
     * @param  array  $patterns
     * @return void
     */
    public function __construct($path, $patterns)
    {
        $this->path = $path;

        $this->patterns = $patterns;
    }

    /**
     * Compile the Route pattern.
     *
     * @return string
     * @throws \DomainException|\LogicException
     */
    public function compile()
    {
        $optionals = 0;

        $variables = array();

        //
        $path = $this->getPath();

        $pattern = preg_replace_callback('#/\{(.*?)(\?)?\}#', function ($matches) use ($path, &$optionals, &$variables)
        {
            list (, $name, $optional) = array_pad($matches, 3, false);

            if (preg_match('/^\d/', $name) === 1) {
                throw new DomainException("Variable name [{$name}] cannot start with a digit in route pattern [{$path}].");
            } else if (in_array($name, $variables)) {
                throw new LogicException("Route pattern [{$path}] cannot reference variable name [{$name}] more than once.");
            } else if (strlen($name) > 32) {
                throw new DomainException("Variable name [{$name}] cannot be longer than 32 characters in route pattern [{$path}].");
            }

            $variables[] = $name;

            //
            $pattern = array_get($this->patterns, $name, '[^/]+');

            $result = sprintf('/(?P<%s>%s)', $name, $pattern);

            if ($optional) {
                $optionals++;

                return '(?:' .$result;
            }

            // The variable is not optional.
            else if ($optionals > 0) {
                throw new LogicException("Route pattern [{$path}] cannot reference variable [{$name}] after optional variables.");
            }

            return $result;

        }, $path);

        return sprintf('#^%s%s$#s', $pattern, str_repeat(')?', $optionals));
    }

    /**
     * Get the route path.
     *
     * @return string
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * Get the route patterns.
     *
     * @return string
     */
    public function getPatterns()
    {
        return $this->patterns;
    }
}
