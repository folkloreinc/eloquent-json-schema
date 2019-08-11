<?php

use Folklore\EloquentJsonSchema\Support\RelationReducer;

class TestChildrenWithPivotReducer extends RelationReducer
{
    protected function getRelationSchemaClass($model, $node, $state)
    {
        return TestChildWithPivotSchema::class;
    }

    protected function getRelationName($model, $node, $state)
    {
        return 'childrenWithPivot';
    }
}
