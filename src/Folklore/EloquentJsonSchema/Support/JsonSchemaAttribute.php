<?php

namespace Folklore\EloquentJsonSchema\Support;

use Folklore\EloquentJsonSchema\Contracts\JsonSchema as JsonSchemaContract;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Contracts\Support\Arrayable;
use Closure;

class JsonSchemaAttribute implements JsonSchemaContract, Arrayable, Jsonable
{
    protected $model;

    protected $schema;

    public function __construct($model, $schema)
    {
        $this->model = $model;
        $this->schema = is_string($schema) ? app($schema) : $schema;
    }

    /**
     * Add a reducer to the schema
     * @param  string|ReducerGetter|ReducerSetter|ReducerSaver $reducer The reducer to add
     * @return $this
     */
    public function withReducer($reducer)
    {
        $reducers = func_num_args() === 1 ? (array)$reducer : func_get_args();
        foreach ($reducers as $reducer) {
            $this->schema->addReducer($reducer);
        }
        return $this;
    }

    public function getType()
    {
        return $this->schema->getType();
    }

    public function setType($type)
    {
        return $this->schema->setType($type);
    }

    public function getProperties()
    {
        return $this->schema->getProperties();
    }

    public function setProperties($properties)
    {
        return $this->schema->setProperties($properties);
    }

    public function getAttributes()
    {
        return $this->schema->getAttributes();
    }

    public function setAttributes($attributes)
    {
        return $this->schema->setAttributes($attributes);
    }

    public function getReducers()
    {
        return $this->schema->getReducers();
    }

    public function setReducers($reducers)
    {
        return $this->schema->setReducers($reducers);
    }

    public function addReducer($reducer)
    {
        return $this->schema->addReducer($reducer);
    }

    public function toArray()
    {
        return $this->schema->toArray();
    }

    public function toJson($options = 0)
    {
        return $this->schema->toJson($options);
    }
}
