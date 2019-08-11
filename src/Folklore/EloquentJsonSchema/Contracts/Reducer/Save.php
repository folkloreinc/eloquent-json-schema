<?php

namespace Folklore\EloquentJsonSchema\Contracts\Reducer;

use Folklore\EloquentJsonSchema\Contracts\HasJsonSchema;
use Folklore\EloquentJsonSchema\Node;

interface Save
{
    public function save($value, Node $node, HasJsonSchema $model);
}
