<?php

use Folklore\EloquentJsonSchema\Support\JsonSchema;

class TestChildSchema extends JsonSchema
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
