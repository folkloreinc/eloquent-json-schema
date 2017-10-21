<?php

namespace Folklore\EloquentJsonSchema;

use Illuminate\Support\Collection;

class NodesCollection extends Collection
{
    public function prependPath($path)
    {
        return $this->each(function ($model) use ($path) {
            $model->prependPath($path);
        });
    }

    public function fromPath($path)
    {
        return $this->reduce(function ($collection, $node) use ($path) {
            if ($node->isInPath($path)) {
                $node->removePath($path);
                $collection->push($node);
            }
            return $collection;
        }, new self());
    }
}
