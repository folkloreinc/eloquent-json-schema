<?php

use Folklore\EloquentJsonSchema\Support\RelationReducer;

class TestChildrenWithPivotReducer extends RelationReducer
{
    protected function getRelationClass($model, $node, $state)
    {
        return TestChildModel::class;
    }

    protected function getRelationSchemaClass($model, $node, $state)
    {
        return TestChildWithPivotSchema::class;
    }

    protected function getRelationSchemaManyClass($model, $node, $state)
    {
        return TestChildrenWithPivotSchema::class;
    }

    protected function getRelationName($model, $node, $state)
    {
        return 'childrenWithPivot';
    }

    protected function getRelationPathColumn($relation)
    {
        return 'handle';
    }
}
