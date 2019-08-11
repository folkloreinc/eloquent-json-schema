<?php

namespace Folklore\EloquentJsonSchema\Contracts\Reducer;

use Folklore\EloquentJsonSchema\Contracts\HasJsonSchema;
use Folklore\EloquentJsonSchema\Node;

interface Get
{
    public function get($value, Node $node, HasJsonSchema $model);
}
