<?php

namespace Quasar\Database;

use Quasar\Database\Connection;
use Quasar\Database\Query\Builder;
use Quasar\Database\Model;


class ModelQuery extends Builder
{
    /**
     * The model instance being queried.
     *
     * @var Quasar\Database\Model
     */
    public $model;


    /**
     * Create a new Builder instance.
     *
     * @return void
     */
    public function __construct(Model $model)
    {
        parent::__construct($model->getConnection(), $model->getTable());

        //
        $this->model = $model;
    }

    /**
     * Execute a query for a single record by ID.
     *
     * @param  int    $id
     * @param  array  $columns
     * @return mixed
     */
    public function find($id, $columns = array('*'))
    {
        $keyName = $this->model->getKeyName();

        return $this->where($keyName, '=', $id)->first($columns);
    }

    /**
     * Find a model by its primary key or throw an exception.
     *
     * @param  mixed  $id
     * @param  array  $columns
     * @return \Nova\Database\ORM\Model|static
     *
     * @throws \Quasar\Database\ModelNotFoundException
     */
    public function findOrFail($id, $columns = array('*'))
    {
        if (! is_null($model = $this->find($id, $columns))) {
            return $model;
        }

        throw (new ModelNotFoundException)->setModel(get_class($this->model));
    }

    /**
     * Find a Model by its primary key.
     *
     * @param  array  $id
     * @param  array  $columns
     * @return array|static
     */
    public function findMany($ids, $columns = array('*'))
    {
        if (empty($ids)) return array();

        $this->query->whereIn($this->model->getKeyName(), $ids);

        return $this->get($columns);
    }

    /**
     * Pluck a single column's value from the first result of a query.
     *
     * @param  string  $column
     * @return mixed
     */
    public function pluck($column)
    {
        $result = $this->first(array($column));

        if (! is_null($result)) {
            // Convert the Model instance to array.
            $result = $result->toArray();
        }

        return (count($result) > 0) ? reset($result) : null;
    }

    /**
     * Execute the query as a "SELECT" statement.
     *
     * @param  array  $columns
     * @return \Database\ORM\Model|static[]
     */
    public function get($columns = array('*'))
    {
        return $this->getModels($columns);
    }

    /**
     + Get the hydrated Models without eager loading.
     *
     * @param  array  $columns
     * @return \Database\ORM\Model|static[]
     */
    public function getModels($columns = array('*'))
    {
        $results = parent::get($columns);

        $connection = $this->model->getConnectionName();

        return array_map(function ($result) use ($connection)
        {
            $model = $this->model->newFromBuilder((array) $result);

            return $model->setConnection($connection);

        }, $results);
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
        $this->aggregate = compact('function', 'columns');

        $previousColumns = $this->columns;

        //
        $results = $this->get($columns);

        $this->aggregate = null;

        $this->columns = $previousColumns;

        if (! empty($results)) {
            $model = first($results);

            // Convert the Model instance to array.
            $result = (array) $model->toArray();

            return $result['aggregate'];
        }
    }

    /**
     * Delete a Record from the database.
     *
     * @return int
     */
    public function delete()
    {
        return parent::delete();
    }
}

