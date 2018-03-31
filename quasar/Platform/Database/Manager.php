<?php

namespace Quasar\Platform\Database;

use Quasar\Platform\Database\Connection;
use Quasar\Platform\Config;

use Exception;


class Manager
{
    /**
     * The Connection instances.
     *
     * @var \Quasar\Platform\Database\Connection[]
     */
    protected $instances = array();


    public function connection($name = 'default')
    {
        if (isset($this->instances[$name])) {
            return $this->instances[$name];
        }

        if (is_null($config = Config::get('database.' .$name))) {
            throw new Exception("Connection [$name] is not defined in configuration");
        }

        return $this->instances[$name] = new Connection($config);
    }

    public function __call($method, $parameters)
    {
        $instance = $this->connection();

        return call_user_func_array(array($instance, $method), $parameters);
    }
}
