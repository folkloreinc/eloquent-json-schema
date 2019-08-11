<?php

use Folklore\EloquentJsonSchema\Support\RelationReducer;

class TestChildrenReducer extends RelationReducer
{
    protected function getRelationSchemaClass($model, $node, $state)
    {
        return TestChildSchema::class;
    }

    protected function getRelationName($model, $node, $state)
    {
        return 'children';
    }
}
