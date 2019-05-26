<?php

namespace Quasar\Database;

use DateTime;
use \PDO;


class Connection
{
    /**
     * @var array  The connection options.
     */
    protected $config = array();

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
     * @var int  The number of active transactions.
     */
    protected $transactions = 0;

    /**
     * All of the queries run against the connection.
     *
     * @var  array
     */
    protected $queryLog = array();

    /**
     * Indicates whether queries are being logged.
     *
     * @var bool
     */
    protected $loggingQueries = true;


    /**
     * Create a new connection instance.
     *
     * @param  array  $config
     * @return void
     */
    public function __construct(array $config)
    {
        $this->config = $config;

        //
        $this->tablePrefix = $config['prefix'];

        if (isset($config['wrapper'])) {
            $this->wrapper = $config['wrapper'];
        }
    }

    /**
     * Begin a Fluent Query against a database table.
     *
     * @param  string  $table
     * @return \Quasar\Database\Query\Builder
     */
    public function table($table)
    {
        return new Query\Builder($this, $table);
    }

    /**
     * Get a new raw query expression.
     *
     * @param  mixed  $value
     * @return \Quasar\Database\Query\Expression
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
        if (! empty($records = $this->select($query, $bindings))) {
            return reset($records);
        }
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
        return $this->run($query, $bindings, function ($me, $query, $bindings)
        {
            $statement = $me->prepare($query);

            $statement->execute($this->prepareBindings($bindings));

            return $statement->fetchAll($me->getFetchMode());
        });
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
        return $this->run($query, $bindings, function ($me, $query, $bindings)
        {
            $statement = $me->prepare($query);

            return $statement->execute($this->prepareBindings($bindings));
        });
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
        return $this->run($query, $bindings, function ($me, $query, $bindings)
        {
            $statement = $me->prepare($query);

            $statement->execute($this->prepareBindings($bindings));

            return $statement->rowCount();
        });
    }

    /**
     * Run a raw, unprepared query against the PDO connection.
     *
     * @param  string  $query
     * @return bool
     */
    public function unprepared($query)
    {
        return $this->run($query, array(), function ($me, $query)
        {
            return (bool) $me->getPdo()->exec($query);
        });
    }

    /**
     * Execute a Closure within a transaction.
     *
     * @param  Closure  $callback
     * @return mixed
     *
     * @throws \Exception
     */
    public function transaction(Closure $callback)
    {
        $this->beginTransaction();

        try {
            $result = $callback($this);

            $this->commit();
        }
        catch (Exception $e) {
            $this->rollBack();

            throw $e;
        }

        return $result;
    }

    /**
     * Start a new database transaction.
     *
     * @return void
     */
    public function beginTransaction()
    {
        $this->transactions++;

        if ($this->transactions == 1) {
            $this->getPdo()->beginTransaction();
        }
    }

    /**
     * Commit the active database transaction.
     *
     * @return void
     */
    public function commit()
    {
        if ($this->transactions == 1) {
            $this->getPdo()->commit();
        }

        $this->transactions--;
    }

    /**
     * Rollback the active database transaction.
     *
     * @return void
     */
    public function rollBack()
    {
        if ($this->transactions == 1) {
            $this->transactions = 0;

            $this->getPdo()->rollBack();
        } else {
            $this->transactions--;
        }
    }

    /**
     * Get the number of active transactions.
     *
     * @return int
     */
    public function transactionLevel()
    {
        return $this->transactions;
    }

    /**
     * Run a SQL statement and log its execution context.
     *
     * @param  string    $query
     * @param  array     $bindings
     * @param  \Closure  $callback
     * @return mixed
     *
     * @throws \Quasar\Database\QueryException
     */
    protected function run($query, $bindings, Closure $callback)
    {
        if (is_null($this->getPdo())) {
            $this->reconnect();
        }

        $start = microtime(true);

        try {
            $result = $this->runQueryCallback($query, $bindings, $callback);
        }
        catch (QueryException $e) {
            $result = $this->tryAgainIfCausedByLostConnection($e, $query, $bindings, $callback);
        }

        $time = round((microtime(true) - $start) * 1000, 2);

        $this->queryLog[] = compact('query', 'bindings', 'time');

        return $result;
    }

    /**
     * Run a SQL statement.
     *
     * @param  string    $query
     * @param  array     $bindings
     * @param  \Closure  $callback
     * @return mixed
     *
     * @throws \Quasar\Database\QueryException
     */
    protected function runQueryCallback($query, $bindings, Closure $callback)
    {
        try {
            return call_user_func($callback, $this, $query, $bindings);
        }
        catch (Exception $e) {
            throw new QueryException($query, $this->prepareBindings($bindings), $e);
        }
    }

    /**
     * Handle a query exception that occurred during query execution.
     *
     * @param  \Quasar\Database\QueryException  $e
     * @param  string    $query
     * @param  array     $bindings
     * @param  \Closure  $callback
     * @return mixed
     *
     * @throws \Quasar\Database\QueryException
     */
    protected function tryAgainIfCausedByLostConnection(QueryException $e, $query, $bindings, Closure $callback)
    {
        static $messages = array(
            'server has gone away',
            'no connection to the server',
            'Lost connection',
            'is dead or not enabled',
            'Error while sending',
            'decryption failed or bad record mac',
            'SSL connection has been closed unexpectedly',
            'Error writing data to the connection',
            'Resource deadlock avoided',
            'Transaction() on null',
            'child connection forced to terminate due to client_idle_limit',
        );

        if (! str_contains($e->getMessage(), static::$messages)) {
            throw $e;
        }

        $this->reconnect();

        return $this->runQueryCallback($query, $bindings, $callback);
    }

    /**
     * Prepare the query bindings for execution.
     *
     * @param  array  $bindings
     * @return array
     */
    public function prepareBindings(array $bindings)
    {
        foreach ($bindings as $key => $value) {
            if ($value instanceof DateTime) {
                $bindings[$key] = $value->format($this->getDateFormat());
            } else if ($value === false) {
                $bindings[$key] = 0;
            }
        }

        return $bindings;
    }

    /**
     * Disconnect from the underlying PDO connection.
     *
     * @return void
     */
    public function disconnect()
    {
        $this->setPdo(null);
    }

    /**
     * Reconnect to the database.
     *
     * @return void
     *
     * @throws \LogicException
     */
    public function reconnect()
    {
        return $this->setPdo(
            $this->createConnection($this->config)
        );
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

        $pdo = new PDO($dsn, $username, $password, $options);

        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, $this->fetchMode);

        return $pdo;
    }

    /**
     * Returns the ID of the last inserted row or sequence value.
     *
     * @param  string|null  $name
     * @return mixed
     */
    public function lastInsertId($name = null)
    {
        $id = $this->getPdo()->lastInsertId($name);

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
            list ($table, $field) = array_pad(explode('.', $matches[1], 2), 2, null);

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
     * Set the PDO connection.
     *
     * @param  \PDO|null  $pdo
     * @return $this
     */
    public function setPdo($pdo)
    {
        if ($this->transactions >= 1) {
            throw new \RuntimeException("Can't swap PDO instance while within transaction.");
        }

        $this->pdo = $pdo;

        return $this;
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
    public function setFetchMode($fetchMode)
    {
        $this->fetchMode = $fetchMode;
    }


    /**
     * Get the connection query log.
     *
     * @return array
     */
    public function getQueryLog()
    {
        return $this->queryLog;
    }

    /**
     * Clear the query log.
     *
     * @return void
     */
    public function flushQueryLog()
    {
        $this->queryLog = array();
    }

    /**
     * Determine or set whether we're logging queries.
     *
     * @return bool
     */
    public function logging($what = null)
    {
        if (is_null($what)) {
            return $this->loggingQueries;
        }

        $this->loggingQueries = (bool) $what;
    }
}
