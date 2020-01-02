<?php

namespace Folklore\EloquentJsonSchema\Contracts;

interface JsonSchema
{
    public function getId();

    public function setId($id);

    public function getType();

    public function setType($type);

    public function getProperties();

    public function setProperties($properties);

    public function getAttributes();

    public function setAttributes($attributes);

    public function getReducers();

    public function setReducers($reducers);

    public function addReducer($reducer);

    public function toArray();
}
