<?php

namespace Folklore\EloquentJsonSchema\Contracts\Reducer;

use Folklore\EloquentJsonSchema\Contracts\HasJsonSchema;
use Folklore\EloquentJsonSchema\NodesCollection;

interface Commit
{
    public function commit($value, NodesCollection $collection, HasJsonSchema $model);
}
