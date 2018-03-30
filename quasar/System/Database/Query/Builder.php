<?php

namespace Quasar\System\Database\Query;

use Quasar\System\Database\Query\Expression;
use Quasar\System\Database\Query\JoinClause;
use Quasar\System\Database\Connection;

use Closure;
use Exception;


class Builder
{
    /**
     * @var \System\Database\Connection
     */
    protected $connection; // The Connection instance.

    /**
     * @var string
     */
    protected $table; // The table which the query is targeting.

    /**
     * The query constraints.
     */
    protected $columns;

    protected $distinct = false;

    protected $bindings = array();
    protected $joins    = array();
    protected $wheres   = array();
    protected $orders   = array();

    protected $offset;
    protected $limit;
    protected $query;


    /**
     * Create a new Builder instance.
     *
     * @param  \System\Database\Connection $connection
     * @param string $table
     * @return void
     */
    public function __construct(Connection $connection, $table = null)
    {
        $this->connection = $connection;

        $this->table = $table;
    }

    /**
     * Set the columns to be selected.
     *
     * @param  array  $columns
     * @return static
     */
    public function select($columns = array('*'))
    {
        $this->columns = is_array($columns) ? $columns : func_get_args();

        return $this;
    }

    /**
     * Force the query to only return distinct results.
     *
     * @return static
     */
    public function distinct()
    {
        $this->distinct = true;

        return $this;
    }

    /**
     * Set the table which the query is targeting.
     *
     * @param  string  $table
     * @return static
     */
    public function from($table)
    {
        $this->from = $table;

        return $this;
    }

    /**
     * Get a single record by ID.
     *
     * @param int $id
     * @param  array  $columns
     * @return mixed|static
     */
    public function find($id, $columns = array('*'))
    {
        return $this->where('id', '=', $id)->first($columns);
    }

    /**
     * Get the first record.
     *
     * @param  array  $columns
     * @return mixed|static
     */
    public function first($columns = array('*'))
    {
        $results = $this->limit(1)->get($columns);

        return (count($results) > 0) ? reset($results) : null;
    }

    /**
     * Get all records.
     *
     * @param $table
     * @return array
     */
    public function get($columns = array('*'))
    {
        if (is_null($this->columns)) {
            $this->columns = array('*');
        }

        $this->query = $this->compileSelect();

        return $this->connection->select($this->query, $this->bindings);
    }

    /**
     * Execute an INSERT query.
     *
     * @param array $data
     * @return array
     */
    public function insert(array $data)
    {
        foreach ($data as $field => $value) {
            $fields[] = $this->wrap($field);

            if ($value instanceof Expression) {
                $value = $value->getValue();
            } else {
                $this->bindings[] = $value;

                $value = '?';
            }

            $values[] = $value;
        }

        $this->query = 'INSERT INTO {' .$this->table .'} (' .implode(', ', $fields) .') VALUES (' .implode(', ', $values) .')';

        return $this->connection->insert($this->query, $this->bindings);
    }

    /**
     * Execute an INSERT query and return the last inserted ID.
     *
     * @param array $data
     * @return array
     */
    public function insertGetId(array $data)
    {
        $this->insert($data);

        return $this->connection->lastInsertId();
    }

    /**
     * Execute an UPDATE query.
     *
     * @param  array  $data
     * @return boolean
     */
    public function update(array $data)
    {
        foreach ($data as $field => $value) {
            if ($value instanceof Expression) {
                $value = $value->getValue();
            } else {
                $this->bindings[] = $value;

                $value = '?';
            }

            $sql[] = $this->wrap($field) .' = ' .$value;
        }

        $this->query = 'UPDATE {' .$this->table .'} SET ' .implode(', ', $sql) .$this->constraints();

        return $this->connection->update($this->query, $this->bindings);
    }

    /**
     * Execute a DELETE query.
     *
     * @return array
     */
    public function delete()
    {
        $this->query = 'DELETE FROM {' .$this->table .'}' .$this->constraints();

        return $this->connection->delete($this->query, $this->bindings);
    }

    /**
     * Add a "JOIN" clause to the query.
     *
     * @param  string  $table
     * @param  string  $first
     * @param  string  $operator
     * @param  string  $two
     * @param  string  $type
     * @param  bool  $where
     * @return static
     */
    public function join($table, $one, $operator = null, $two = null, $type = 'inner', $where = false)
    {
        if ($one instanceof Closure) {
            $this->joins[] = new JoinClause($this, $type, $table);

            call_user_func($one, end($this->joins));
        } else {
            $join = new JoinClause($this, $type, $table);

            $this->joins[] = $join->on($one, $operator, $two, 'and', $where);
        }

        return $this;
    }

    /**
     * Add a "JOIN WHERE" clause to the query.
     *
     * @param  string  $table
     * @param  string  $first
     * @param  string  $operator
     * @param  string  $two
     * @param  string  $type
     * @return static
     */
    public function joinWhere($table, $one, $operator, $two, $type = 'inner')
    {
        return $this->join($table, $one, $operator, $two, $type, true);
    }

    /**
     * Add a "LEFT JOIN" to the query.
     *
     * @param  string  $table
     * @param  string  $first
     * @param  string  $operator
     * @param  string  $second
     * @return static
     */
    public function leftJoin($table, $first, $operator = null, $second = null)
    {
        return $this->join($table, $first, $operator, $second, 'left');
    }

    /**
     * Add a "LEFT JOIN WHERE" clause to the query.
     *
     * @param  string  $table
     * @param  string  $first
     * @param  string  $operator
     * @param  string  $two
     * @return static
     */
    public function leftJoinWhere($table, $one, $operator, $two)
    {
        return $this->joinWhere($table, $one, $operator, $two, 'left');
    }

    /**
     * Add a "WHERE" clause to the query.
     *
     * @param string $field
     * @param string|null $operator
     * @param mixed|null $value
     * @return static
     */
    public function where($column, $operator = null, $value = null, $boolean = 'and')
    {
        if (func_num_args() == 2) {
            list ($value, $operator) = array($operator, '=');
        }

        if ($column instanceof Closure) {
            return $this->whereNested($column, $boolean);
        } else if ($value instanceof Closure) {
            return $this->whereSub($column, $operator, $value, $boolean);
        }

        $type = 'Basic';

        $this->wheres[] = compact('type', 'column', 'operator', 'value', 'boolean');

        return $this;
    }

    /**
     * Add an "OR WHERE" clause to the query.
     *
     * @param  string  $column
     * @param  string  $operator
     * @param  mixed   $value
     * @return static
     */
    public function orWhere($column, $operator = null, $value = null)
    {
        return $this->where($column, $operator, $value, 'or');
    }

    /**
     * Add a nested where statement to the query.
     *
     * @param  \Closure $callback
     * @param  string   $boolean
     * @return static
     */
    public function whereNested(Closure $callback, $boolean = 'and')
    {
        $query = new static($this->connection, $this->from);

        call_user_func($callback, $query);

        if (! empty($query->wheres)) {
            $type = 'Nested';

            $this->wheres[] = compact('type', 'query', 'boolean');
        }

        return $this;
    }

    /**
     * Add a full sub-select to the query.
     *
     * @param  string   $column
     * @param  string   $operator
     * @param  \Closure $callback
     * @param  string   $boolean
     * @return static
     */
    protected function whereSub($column, $operator, Closure $callback, $boolean)
    {
        $type = 'Sub';

        //
        $query = new static($this->connection);

        call_user_func($callback, $query);

        $this->wheres[] = compact('type', 'column', 'operator', 'query', 'boolean');

        return $this;
    }

    /**
     * Add a raw "WHERE" condition to the query.
     *
     * @param  string  $sql
     * @param  array   $bindings
     * @param  string  $boolean
     * @return static
     */
    public function whereRaw($sql, $bindings = array(), $boolean = 'and')
    {
        $type = 'Raw';

        $this->wheres[] = compact('type', 'sql', 'boolean');

        $this->bindings = array_merge($this->bindings, $bindings);

        return $this;
    }

    /**
     * Add a raw "OR WHERE" condition to the query.
     *
     * @param  string  $where
     * @param  array   $bindings
     * @return \static
     */
    public function orWhereRaw($where, $bindings = array())
    {
        return $this->whereRaw($where, $bindings, 'or');
    }

    /**
     * Determine if any rows exist for the current query.
     *
     * @return bool
     */
    public function exists()
    {
        return $this->count() > 0;
    }

    /**
     * Retrieve the "COUNT" result of the query.
     *
     * @param  string  $column
     * @return int
     */
    public function count($column = '*')
    {
        return (int) $this->aggregate('count', array($column));
    }

    /**
     * Execute an aggregate function on the database.
     *
     * @param  string  $function
     * @param  array   $columns
     * @return mixed
     */
    public function aggregate($function, $columns = array('*'))
    {
        $column = $this->columnize($columns);

        if ($this->distinct && ($column !== '*')) {
            $column = 'DISTINCT ' .$column;
        }

        $this->query = 'SELECT ' .$function .'(' .$column .') AS aggregate FROM {' .$this->table .'}' .$this->constraints();

        $result = $this->connection->selectOne($this->query, $this->bindings);

        // Reset the bindings.
        $this->bindings = array();

        if (! is_null($result)) {
            $result = (array) $result;

            return $result['aggregate'];
        }
    }

    /**
     * Set the "OFFSET" value of the query.
     *
     * @param  int  $value
     * @return static
     */
    public function offset($value)
    {
        $this->offset = max(0, (int) $value);

        return $this;
    }

    /**
     * Set the "LIMIT" value of the query.
     *
     * @param int $limit
     * @return static
     */
    public function limit($value)
    {
        if ($value > 0) {
            $this->limit = (int) $value;
        }

        return $this;
    }

    /**
     * Add an "ORDER BY" clause to the query.
     *
     * @param string $column
     * @param string $direction
     * @return static
     */
    public function orderBy($column, $direction = 'asc')
    {
        $direction = (strtolower($direction) == 'asc') ? 'ASC' : 'DESC';

        $this->orders[] = compact('column', 'direction');

        return $this;
    }

    /**
     * Compute the SQL and parameters for constraints.
     *
     * @return string
     */
    protected function constraints()
    {
        $query = '';

        // Joins
        $sql = array();

        foreach ($this->joins as $join) {
            $clauses = array();

            foreach ($join->clauses as $clause) {
                $first = $this->wrap($clause['first']) ;

                $second = ($clause['where'] == true) ? '?' : $this->wrap($clause['second']);

                $clauses[] = strtoupper($clause['boolean']) .' ' .$first .' ' .$clause['operator'] .' ' .$second;
            }

            $clauses = preg_replace('/AND |OR /', '', implode(' ', $clauses), 1);

            $sql[] = strtoupper($join->type) .' JOIN ' .$this->wrap($join->table) .' ON ' .$clauses;
        }

        if (! empty($sql)) {
            $query .= implode(' ', $sql);
        }

        // Wheres
        if (! is_null($sql = $this->compileWheres())) {
            $query .= ' WHERE ' .$sql;
        }

        // Orders
        $sql = array();

        foreach ($this->orders as $order) {
            $sql[] = $this->wrap($order['column']) .' ' .$order['direction'];
        }

        if (! empty($sql)) {
            $query .= ' ORDER BY ' .implode(', ', $sql);
        }

        // Limits
        if (isset($this->limit)) {
            $query .= ' LIMIT ' .$this->limit;
        }

        if (isset($this->offset)) {
            $query .= ' OFFSET ' .$this->offset;
        }

        return $query;
    }

    /**
     * Compile the "WHERE" portions of the query.
     *
     * @return string
     */
    protected function compileWheres()
    {
        $sql = array();

        foreach ($this->wheres as $where) {
            $sql[] = strtoupper($where['boolean']) .' ' .$this->compileWhere($where);
        }

        if (! empty($sql)) {
            return preg_replace('/AND |OR /', '', implode(' ', $sql), 1);
        }
    }

    /**
     * Compile a WHERE condition.
     *
     * @param  array  $where
     * @return string
     */
    protected function compileWhere(array $where)
    {
        extract($where);

        //
        $column = $this->wrap($column);

        if ($type === 'Nested') {
            $sql = '(' .$query->compileWheres() .')';

            $this->bindings = array_merge($this->bindings, $query->bindings);

            return $sql;
        } else if ($type === 'Sub') {
            $sql = $column .' ' .$operator .' (' .$query->compileSelect() .')';

            $this->bindings = array_merge($this->bindings, $query->bindings);

            return $sql;
        } else if ($type === 'Raw') {
            return $sql;
        }

        $not = ($operator !== '=') ? 'NOT ' : '';

        if (is_array($value)) {
            $this->bindings = array_merge($this->bindings, $value);

            $values = array_fill(0, count($value), '?');

            return $column .' ' .$not .'IN (' .implode(', ', $values) .')';
        } else if (is_null($value)) {
            return $column .' IS ' .$not .'NULL';
        }

        // The value is an Expression instance?
        else if ($value instanceof Expression) {
            $value = $value->getValue();
        } else {
            $this->bindings[] = $value;

            $value = '?';
        }

        return $column .' ' .$operator .' ' .$value;
    }

    /**
     * Compile a select query into SQL.
     *
     * @return string
     */
    public function compileSelect()
    {
        if (is_null($this->columns)) {
            $this->columns = array('*');
        }

        $select = $this->distinct ? 'SELECT DISTINCT' : 'SELECT';

        return  $select .' ' .$this->columnize($this->columns) .' FROM {' .$this->table .'}' .$this->constraints();
    }

    /**
     * Add a binding to the query.
     *
     * @param  mixed  $value
     * @return static
     */
    public function addBinding($value)
    {
        $this->bindings[] = $value;

        return $this;
    }

    /**
     * Convert an array of column names into a delimited string.
     *
     * @param  array   $columns
     * @return string
     */
    public function columnize(array $columns)
    {
        return implode(', ', array_map(array($this, 'wrap'), $columns));
    }

    /**
     * Wrap a value in keyword identifiers.
     *
     * @param  string  $value
     * @return string
     */
    protected function wrap($value)
    {
        return $this->connection->wrap($value);
    }

    /**
     * Get the last executed SQL query.
     *
     * @return string|null
     */
    public function lastQuery()
    {
        return $this->query;
    }

    /**
     * Magic call.
     *
     * @param  string  $method
     * @param  array  $parameters
     * @return mixed|null
     * @throws \Exception
     */
    public function __call($method, $parameters)
    {
        if (in_array($method, array('min', 'max', 'sum', 'avg'))) {
            $column = reset($parameters);

            return $this->aggregate($method, array($column));
        }

        throw new Exception("Method [$method] not found.");
    }
}
