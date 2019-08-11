<?php

use Folklore\EloquentJsonSchema\Support\Reducer;

use Folklore\EloquentJsonSchema\Contracts\HasJsonSchema;
use Folklore\EloquentJsonSchema\Node;
use Folklore\EloquentJsonSchema\Support\Utils;

class TestSlugReducer extends Reducer
{
    public function set($value, Node $node, HasJsonSchema $model)
    {
        if ($node->path !== 'slug') {
            return $value;
        }
        $slug = Utils::getPath($value, $node->path);
        $name = Utils::getPath($value, 'name');
        $value = Utils::setPath($value, $node->path, isset($slug) ? $slug : str_slug($name));
        return $value;
    }
}
