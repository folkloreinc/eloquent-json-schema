<?php

namespace Folklore\EloquentJsonSchema\Contracts;

interface HasJsonSchema
{
    public function validateJsonSchemaAttributes();

    public function saveJsonSchemaAttributes();

    public function getAttributeJsonSchema($key);

    public function attributeHasJsonSchema($key);
}
