<?php

use Folklore\EloquentJsonSchema\Support\JsonSchema;

class TestDataSchema extends JsonSchema
{
    protected function properties()
    {
        return [
            'type' => [
                'type' => 'string',
            ],
            'name' => [
                'type' => 'string',
            ],
            'slug' => [
                'type' => 'string',
            ],
            'children' => TestChildrenSchema::class,
            'childrenWithPivot' => TestChildrenWithPivotSchema::class,
            'child' => TestChildSchema::class,
        ];
    }

    protected function reducers()
    {
        return [
            TestSlugReducer::class,
            TestChildrenReducer::class,
            TestChildrenWithPivotReducer::class,
        ];
    }
}
