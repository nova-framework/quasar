<?php

namespace Quasar\Platform\Database;

use Quasar\Platform\Database\Manager;


class Model
{
    /**
     * The Connection name.
     *
     * @var string
     */
    protected $connection = 'default';

    /**
     * The table associated with the Model.
     *
     * @var string
     */
    protected $table;

    /**
     * The primary key for the Model.
     *
     * @var string
     */
    protected $primaryKey = 'id';


    /**
     * Create a new Model instance.
     *
     * @param  string  $connection
     * @return void
     */
    public function __construct($connection = null)
    {
        if (! is_null($connection)) {
            $this->connection = $connection;
        }

        if (isset($this->table)) {
            return;
        }

        // Guessing the table like: 'App\Models\PostComments' -> 'post_comments'

        $this->table = strtolower(preg_replace('~(?<=\\w)([A-Z])~', '_$1',
            basename(str_replace('\\', '/', static::class))
        ));
    }

    /**
     * Find a Record by its primary key.
     *
     * @param  mixed  $id
     * @param  array  $columns
     * @return Model
     */
    public function find($id, $columns = array('*'))
    {
        return $this->newQuery()->where($this->getKeyName(), $id)->first($columns);
    }

    /**
     * Find many Records by their primary key.
     *
     * @param  array  $ids
     * @param  array  $columns
     * @return Model
     */
    public function findMany(array $ids, $columns = array('*'))
    {
        return $this->newQuery()->where($this->getKeyName(), $ids)->get($columns);
    }

    /**
     * Get all of the Records from the database.
     *
     * @param  array  $columns
     * @return array
     */
    public function findAll($columns = array('*'))
    {
        return $this->newQuery()->get($columns);
    }

    /**
     * Insert a new Record and get the value of the primary key.
     *
     * @param  array   $values
     * @return int
     */
    public function insert(array $values)
    {
        return $this->newQuery()->insert($values);
    }

    /**
     * Update the Record in the database.
     *
     * @param  mixed  $id
     * @param  array  $attributes
     * @return mixed
     */
    public function update($id, array $attributes = array())
    {
        return $this->newQuery()->where($this->getKeyName(), $id)->update($attributes);
    }

    /**
     * Delete the Record from the database.
     *
     * @return bool|null
     */
    public function delete($id)
    {
        $this->newQuery()->where($this->getKeyName(), $id)->delete();

        return true;
    }

    /**
     * Get a new Query for the Model's table.
     *
     * @return \System\Database\Query\Builder
     */
    public function newQuery()
    {
        return $this->getConnection()->table($this->table);
    }

    /**
     * Get the Table for the Model.
     *
     * @return string
     */
    public function getTable()
    {
        return $this->table;
    }

    /**
     * Get the Table for the Model.
     *
     * @return string
     */
    public static function getTableName()
    {
        $model = new static();

        return $model->getTable();
    }

    /**
     * Get the Primary Key for the Model.
     *
     * @return string
     */
    public function getKeyName()
    {
        return $this->primaryKey;
    }

    /**
     * Get the Connection instance.
     *
     * @return \System\Database\Connection
     */
    public function getConnection()
    {
        $manager = Manager::getInstance();

        return $manager->connection($this->connection);
    }

    /**
     * Get the current Connection name for the Model.
     *
     * @return string
     */
    public function getConnectionName()
    {
        return $this->connection;
    }

    /**
     * Handle dynamic method calls into the method.
     *
     * @param  string  $method
     * @param  array   $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        $query = $this->newQuery();

        return call_user_func_array(array($query, $method), $parameters);
    }
}
