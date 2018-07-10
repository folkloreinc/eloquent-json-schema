<?php

use Folklore\EloquentJsonSchema\Support\JsonSchema;

class TestChildWithPivotSchema extends JsonSchema
{
    protected function properties()
    {
        return [
            'id' => [
                'type' => 'string',
            ],
        ];
    }
}
