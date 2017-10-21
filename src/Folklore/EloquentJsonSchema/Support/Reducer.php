<?php

namespace Folklore\EloquentJsonSchema\Support;

use Folklore\EloquentJsonSchema\Contracts\ReducerGetter;
use Folklore\EloquentJsonSchema\Contracts\ReducerSetter;
use Folklore\EloquentJsonSchema\Contracts\ReducerSaver;
use Folklore\EloquentJsonSchema\Contracts\HasJsonSchema as HasJsonSchemaContract;
use Folklore\EloquentJsonSchema\Node;

class Reducer implements ReducerGetter, ReducerSetter, ReducerSaver
{
    public function get(HasJsonSchemaContract $model, Node $node, $value)
    {
        return $value;
    }

    public function set(HasJsonSchemaContract $model, Node $node, $value)
    {
        return $value;
    }

    public function save(HasJsonSchemaContract $model, Node $node, $value)
    {
        return $value;
    }
}
