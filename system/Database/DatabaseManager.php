<?php

namespace Quasar\Database;

use Quasar\Config;

use Exception;


class DatabaseManager
{
    /**
     * The Connection instances.
     *
     * @var \Quasar\Database\Connection[]
     */
    protected $instances = array();


    public function connection($name = null)
    {
        if (is_null($name)) {
            $name = 'default';
        }

        if (isset($this->instances[$name])) {
            return $this->instances[$name];
        }

        //
        else if (is_null($config = Config::get('database.' .$name))) {
            throw new Exception("Connection [$name] is not defined in configuration");
        }

        return $this->instances[$name] = $connection = new Connection($config);

        // Set the fetch mode on Connection instance.
        $fetchMode = array_get($config, 'fetch');

        $connection->setFetchMode($fetchMode);

        return $connection;
    }

    public function __call($method, $parameters)
    {
        $instance = $this->connection();

        return call_user_func_array(array($instance, $method), $parameters);
    }
}
