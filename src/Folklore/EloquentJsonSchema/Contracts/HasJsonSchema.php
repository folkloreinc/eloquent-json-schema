<?php

namespace Folklore\EloquentJsonSchema\Contracts;

interface HasJsonSchema
{
    public function validateJsonSchemaAttributes();

    public function saveJsonSchemaAttributes();

    public function getJsonSchemaAttributes();

    public function getAttributeJsonSchema($key);

    public function getAttributeJsonSchemaReducers($key);

    public function hasJsonSchema($key);

    public function getJsonSchemas();

    public function setJsonSchemas($schemas);

    public function getJsonSchemaReducers();

    public function setJsonSchemaReducers($key, $reducers = null);
}
