<?php

use Folklore\EloquentJsonSchema\Support\JsonSchema;

class TestChildDataSchema extends JsonSchema
{
    protected function properties()
    {
        return [
            'name' => [
                'type' => 'string',
            ],
        ];
    }
}
