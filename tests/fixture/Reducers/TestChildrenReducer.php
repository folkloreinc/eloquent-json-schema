<?php

use Folklore\EloquentJsonSchema\Support\RelationReducer;

class TestChildrenReducer extends RelationReducer
{
    protected function getRelationClass($model, $node, $state)
    {
        return TestChildModel::class;
    }

    protected function getRelationSchemaClass($model, $node, $state)
    {
        return TestChildSchema::class;
    }

    protected function getRelationSchemaManyClass($model, $node, $state)
    {
        return TestChildrenSchema::class;
    }

    protected function getRelationName($model, $node, $state)
    {
        return 'children';
    }

    protected function getRelationPathColumn($relation)
    {
        return 'test_handle';
    }
}
