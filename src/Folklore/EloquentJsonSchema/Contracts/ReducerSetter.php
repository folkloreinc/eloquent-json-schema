<?php

namespace Folklore\EloquentJsonSchema\Contracts;

use Folklore\EloquentJsonSchema\Node;

interface ReducerSetter
{
    public function set(HasJsonSchema $model, Node $node, $value);
}
