<?php

namespace Folklore\EloquentJsonSchema\Contracts;

use Folklore\EloquentJsonSchema\Node;

interface ReducerSaver
{
    public function save(HasJsonSchema $model, Node $node, $value);
}
