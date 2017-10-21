<?php

use Folklore\EloquentJsonSchema\Support\JsonSchema;

class TestChildrenSchema extends JsonSchema
{
    protected function type()
    {
        return 'array';
    }

    protected function items()
    {
        return TestChildSchema::class;
    }
}
