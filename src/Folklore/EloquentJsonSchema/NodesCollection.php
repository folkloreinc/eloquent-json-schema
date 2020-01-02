<?php

namespace Folklore\EloquentJsonSchema;

use Illuminate\Support\Collection;
use Folklore\EloquentJsonSchema\Contracts\JsonSchema as JsonSchemaContract;

class NodesCollection extends Collection
{
    protected $attribute;

    public static function makeFromSchema($schema, $data = null, $root = null)
    {
        $schema = is_string($schema) ? app($schema) : $schema;
        $nodes = static::getNodes($schema, $root);
        $dataArray = !is_null($data) ? json_decode(json_encode($data), true) : [];
        $dataPaths = is_array($dataArray) ? array_keys(array_dot($dataArray)) : [];
        return $nodes->reduce(function ($collection, $node) use ($dataPaths, $data) {
            $paths = static::getMatchingPaths($dataPaths, $node->path);
            foreach ($paths as $path) {
                $newNode = clone $node;
                $newNode->path = $path;
                $collection->push($newNode);
            }
            return $collection;
        }, new static());
    }

    protected static function getNodes(JsonSchemaContract $schema, $root = null)
    {
        $type = $schema->getType();

        // get properties
        $properties = [];
        if ($type === 'object') {
            $properties = $schema->getProperties();
        } elseif ($type === 'array') {
            $items = $schema->getItems();
            if ($items instanceof JsonSchemaContract || (is_array($items) && isset($items['type']))) {
                $properties = [
                    '*' => $items
                ];
            } else {
                $properties = $items;
            }
        }
        // @TODO Handle other types

        $nodes = new static();
        foreach ($properties as $name => $propertySchema) {
            $propertyPath = $name;
            $schemaNode = new Node();
            $schemaNode->path = $propertyPath;
            $schemaNode->schema = $propertySchema;

            if ($propertySchema instanceof JsonSchemaContract) {
                $schemaNode->type = $propertySchema->geType();
                $propertyNodes = static::getNodes($propertySchema)->prependPath($propertyPath);
                $nodes = $nodes->push($schemaNode)->merge($propertyNodes);
            } else {
                $schemaNode->type = array_get($propertySchema, 'type');
                $nodes->push($schemaNode);
            }
        }
        return $root !== null ? $nodes->fromPath($root) : $nodes;
    }

    protected static function getMatchingPaths($dataPaths, $path)
    {
        if (sizeof(explode('*', $path)) <= 1) {
            return [$path];
        }

        $matchingPaths = [];
        $pattern = !empty($path) && $path !== '*' ?
            '/^('.str_replace('\*', '[^\.]+', preg_quote($path)).')/' : '/^(.*)/';
        foreach ($dataPaths as $dataPath) {
            if (preg_match($pattern, $dataPath, $matches)) {
                if (!in_array($matches[1], $matchingPaths)) {
                    $matchingPaths[] = $matches[1];
                }
            }
        }
        return $matchingPaths;
    }

    public function setAttribute($attribute)
    {
        $this->attribute = $attribute;
        return $this;
    }

    public function getAttribute()
    {
        return $this->attribute;
    }

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
        }, new static);
    }
}
