<?php

namespace Quasar\System\Database;

use \PDO;


class Connection
{
    /**
     * @var  \PDO  The active PDO connection.
     */
    protected $pdo;

    /**
     * @var  int  The default fetch mode of the connection.
     */
    protected $fetchMode = PDO::FETCH_OBJ;

    /**
     * @var  string  The table prefix for the connection.
     */
    protected $tablePrefix = '';

    /**
     * @var  string  The table prefix for the connection.
     */
    protected $wrapper = '`';


    /**
     * Create a new connection instance.
     *
     * @param  array  $config
     * @return void
     */
    public function __construct(array $config)
    {
        $this->tablePrefix = $config['prefix'];

        $this->wrapper = $config['wrapper'];

        $this->pdo = $this->createConnection($config);

        //
        $this->setFetchMode();
    }

    /**
     * Create a new PDO connection.
     *
     * @param  array  $config
     * @return PDO
     */
    protected function createConnection(array $config)
    {
        extract($config);

        $dsn = "$driver:host={$hostname};dbname={$database}";

        $options = array(
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_STRINGIFY_FETCHES  => false,
            PDO::ATTR_EMULATE_PREPARES   => false,

            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$charset} COLLATE '$collation'",
        );

        return new PDO($dsn, $username, $password, $options);
    }

    /**
     * Begin a Fluent Query against a database table.
     *
     * @param  string  $table
     * @return \System\Database\Query\Builder
     */
    public function table($table)
    {
        return new Query\Builder($this, $table);
    }

    /**
     * Get a new raw query expression.
     *
     * @param  mixed  $value
     * @return \System\Database\Query\Expression
     */
    public function raw($value)
    {
        return new Query\Expression($value);
    }

    /**
     * Run a select statement and return a single result.
     *
     * @param  string  $query
     * @param  array   $bindings
     * @return mixed
     */
    public function selectOne($query, $bindings = array())
    {
        $statement = $this->prepare($query);

        $statement->execute($bindings);

        return $statement->fetch($this->getFetchMode()) ?: null;
    }

    /**
     * Run a select statement against the database.
     *
     * @param  string  $query
     * @param  array   $bindings
     * @return array
     */
    public function select($query, array $bindings = array())
    {
        $statement = $this->prepare($query);

        $statement->execute($bindings);

        return $statement->fetchAll($this->getFetchMode());
    }

    /**
     * Run an insert statement against the database.
     *
     * @param  string  $query
     * @param  array   $bindings
     * @return bool
     */
    public function insert($query, array $bindings = array())
    {
        return $this->statement($query, $bindings);
    }

    /**
     * Run an update statement against the database.
     *
     * @param  string  $query
     * @param  array   $bindings
     * @return int
     */
    public function update($query, array $bindings = array())
    {
        return $this->affectingStatement($query, $bindings);
    }

    /**
     * Run a delete statement against the database.
     *
     * @param  string  $query
     * @param  array   $bindings
     * @return int
     */
    public function delete($query, array $bindings = array())
    {
        return $this->affectingStatement($query, $bindings);
    }

    /**
     * Execute an SQL statement and return the boolean result.
     *
     * @param  string  $query
     * @param  array   $bindings
     * @return bool
     */
    public function statement($query, array $bindings = array())
    {
        $statement = $this->prepare($query);

        return $statement->execute($bindings);
    }

    /**
     * Run an SQL statement and get the number of rows affected.
     *
     * @param  string  $query
     * @param  array   $bindings
     * @return int
     */
    public function affectingStatement($query, array $bindings = array())
    {
        $statement = $this->prepare($query);

        $statement->execute($bindings);

        return $statement->rowCount();
    }

    /**
     * Returns the ID of the last inserted row or sequence value.
     *
     * @return mixed
     */
    public function lastInsertId()
    {
        $id = $this->getPdo()->lastInsertId();

        return is_numeric($id) ? (int) $id : $id;
    }

    /**
     * Parse the table variables and add the table prefix.
     *
     * @param  string  $query
     * @return string
     */
    public function prepare($query)
    {
        $prefix = $this->getTablePrefix();

        $query = preg_replace_callback('#\{(.*?)\}#', function ($matches) use ($prefix)
        {
            @list ($table, $field) = explode('.', $matches[1], 2);

            $result = $this->wrap($prefix .$table);

            if (! is_null($field)) {
                $result .= '.' . $this->wrap($field);
            }

            return $result;

        }, $query);

        return $this->getPdo()->prepare($query);
    }

    /**
     * Wrap a single string in keyword identifiers.
     *
     * @param  string  $value
     * @return string
     */
    public function wrap($value)
    {
        if ($value === '*') {
            return $value;
        }

        $wrapper = $this->getWrapper();

        return $wrapper .$value .$wrapper;
    }

    /**
     * Get the PDO instance.
     *
     * @return PDO
     */
    public function getPdo()
    {
        return $this->pdo;
    }

    /**
     * Returns the wrapper string.
     *
     * @return string
     */
    public function getWrapper()
    {
        return $this->wrapper;
    }

    /**
     * Get the table prefix for the connection.
     *
     * @return string
     */
    public function getTablePrefix()
    {
        return $this->tablePrefix;
    }

    /**
     * Get the default fetch mode for the connection.
     *
     * @return int
     */
    public function getFetchMode()
    {
        return $this->fetchMode;
    }

    /**
     * Set the default fetch mode for the connection.
     *
     * @param  int  $fetchMode
     * @return int
     */
    public function setFetchMode($fetchMode = null)
    {
        if (! is_null($fetchMode)) {
            $this->fetchMode = $fetchMode;
        }

        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, $this->fetchMode);
    }
}
