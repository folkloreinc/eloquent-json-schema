<?php

namespace Folklore\EloquentJsonSchema\Support;

use Folklore\EloquentJsonSchema\Contracts\Reducer\Get;
use Folklore\EloquentJsonSchema\Contracts\Reducer\Set;
use Folklore\EloquentJsonSchema\Contracts\HasJsonSchema as HasJsonSchemaContract;
use Folklore\EloquentJsonSchema\Node;
use Folklore\EloquentJsonSchema\NodesCollection;

class Reducer implements Get, Set
{
    public function get($value, Node $node, HasJsonSchemaContract $model)
    {
        return $value;
    }

    public function set($value, Node $node, HasJsonSchemaContract $model)
    {
        return $value;
    }
}
