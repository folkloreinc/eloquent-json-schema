<?php

namespace Folklore\EloquentJsonSchema\Contracts\Reducer;

use Folklore\EloquentJsonSchema\Contracts\HasJsonSchema;
use Folklore\EloquentJsonSchema\Node;

interface Set
{
    public function set($value, Node $node, HasJsonSchema $model);
}
