<?php

use Folklore\EloquentJsonSchema\Support\Reducer;

use Folklore\EloquentJsonSchema\Contracts\HasJsonSchema;
use Folklore\EloquentJsonSchema\Node;

class TestSlugReducer extends Reducer
{
    public function set(HasJsonSchema $model, Node $node, $value)
    {
        if ($node->path !== 'slug') {
            return $value;
        }
        $value['slug'] = array_get($value, 'slug', str_slug(array_get($value, 'name', '')));
        return $value;
    }
}
