<?php

namespace Folklore\EloquentJsonSchema\Contracts;

use Folklore\EloquentJsonSchema\Node;

interface ReducerGetter
{
    public function get(HasJsonSchema $model, Node $node, $value);
}
