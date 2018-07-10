<?php

use Folklore\EloquentJsonSchema\Support\JsonSchema;

class TestChildrenWithPivotSchema extends JsonSchema
{
    protected function type()
    {
        return 'array';
    }

    protected function items()
    {
        return TestChildWithPivotSchema::class;
    }
}
