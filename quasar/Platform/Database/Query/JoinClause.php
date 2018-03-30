<?php

namespace Quasar\Platform\Database\Query;

use Quasar\Platform\Database\Query\Builder as QueryBuilder;


class JoinClause
{
    /**
    * @var \Database\Query\Builder  The QueryBuilder instance.
    */
    public $query;

    /**
    * @var string  The type of join being performed.
    */
    public $type;

    /**
    * @var string  The table the join clause is joining to.
    */
    public $table;

    /**
    * @var array  The "on" clauses for the join.
    */
    public $clauses = array();

    /**
    * Create a new join clause instance.
    *
    * @param  \Database\Query  $query
    * @param  string  $type
    * @param  string  $table
    * @return void
    */
    public function __construct(QueryBuilder $query, $type, $table)
    {
        $this->type  = $type;
        $this->query = $query;
        $this->table = $table;
    }

    /**
    * Add an "ON" clause to the join.
    *
    * @param  string  $first
    * @param  string  $operator
    * @param  string  $second
    * @param  string  $boolean
    * @param  bool  $where
    * @return \System\Database\Query\JoinClause
    */
    public function on($first, $operator, $second, $boolean = 'and', $where = false)
    {
        $this->clauses[] = compact('first', 'operator', 'second', 'boolean', 'where');

        if ($where) {
            $this->query->addBinding($second);
        }

        return $this;
    }

    /**
    * Add an "OR ON" clause to the join.
    *
    * @param  string  $first
    * @param  string  $operator
    * @param  string  $second
    * @return \System\\Database\Query\JoinClause
    */
    public function orOn($first, $operator, $second)
    {
        return $this->on($first, $operator, $second, 'or');
    }

    /**
    * Add an "ON WHERE" clause to the join.
    *
    * @param  string  $first
    * @param  string  $operator
    * @param  string  $second
    * @param  string  $boolean
    * @return \System\\Database\Query\JoinClause
    */
    public function where($first, $operator, $second, $boolean = 'and')
    {
        return $this->on($first, $operator, $second, $boolean, true);
    }

    /**
    * Add an "OR ON WHERE" clause to the join.
    *
    * @param  string  $first
    * @param  string  $operator
    * @param  string  $second
    * @param  string  $boolean
    * @return \System\\Database\Query\JoinClause
    */
    public function orWhere($first, $operator, $second)
    {
        return $this->on($first, $operator, $second, 'or', true);
    }
}
