<?php

namespace Folklore\EloquentJsonSchema\Support;

use Folklore\EloquentJsonSchema\Contracts\Support\Valueable;

class RelationReducerCommitter implements Valueable
{
    protected $value;
    protected $relations = [];

    public function __construct($value)
    {
        $this->value = $value;
    }

    public function addRelation($relation, $path, $model)
    {
        if (!isset($this->relations[$relation])) {
            $this->relations[$relation] = [];
        }
        $this->relations[$relation][$path] = $model;
        return $this;
    }

    public function getValue()
    {
        return $this->value;
    }

    public function toValue()
    {
        return $this->getValue();
    }

    public function getValueAtPath($path)
    {
        return Utils::getPath($this->value, $path);
    }

    public function setValueAtPath($path, $value)
    {
        $this->value = Utils::setPath($this->value, $path, $value);
        return $this;
    }

    public function commit($reducer, $model)
    {
        dump(sprintf('%s::commit %s', get_class($reducer), json_encode($this->value)));
        $currentMetadata = $this->getMetadata($reducer);
        $newMetadata = [];
        if (!sizeof($this->relations)) {
            foreach ($metadata as $relation => $paths) {
                $keysToDetach = array_unique(array_values($paths));
                foreach ($keysToDetach as $keyToDetach) {
                    $this->detachRelation($model, $relation, $keyToDetach);
                }
            }
        } else {
            foreach ($this->relations as $relation => $paths) {
                $current = array_get($currentMetadata, $relation, []);
                $newMetadata[$relation] = [];
                $currentRelationKeys = array_unique(array_values($current));
                $newRelationKeys = [];
                foreach ($paths as $path => $relationModel) {
                    if (!is_null($relationModel)) {
                        $relationKey = (string)$relationModel->getKey();
                        $newMetadata[$relation][$path] = $relationKey;
                        $newRelationKeys[] = $relationKey;
                    }
                }
                $newRelationKeys = array_unique($newRelationKeys);
                $keysToDetach = array_diff($currentRelationKeys, $newRelationKeys);
                $keysToAttach = array_diff($newRelationKeys, $currentRelationKeys);
                foreach ($keysToDetach as $keyToDetach) {
                    $this->detachRelation($model, $relation, $keyToDetach);
                }
                foreach ($keysToAttach as $keyToAttach) {
                    $this->attachRelation($model, $relation, $keyToAttach);
                }
            }
        }
        dump($model);
        $this->setMetadata($reducer, $newMetadata);
        dump(sprintf('%s::commitEnd %s', get_class($reducer), json_encode($this->value)));
        return $this->value;
    }

    protected function detachRelation($model, $relation, $relationKey)
    {
        $relation = $model->$relation();
        if ($relation instanceof BelongsToMany) {
            $relation->detach($relationKey);
        } else {
            $relationModel = $this->getRelationModel($relation, $relationKey);
            $relationModel->setAttribute($relation->getForeignKeyName(), null);
            $relationModel->save();
        }
    }

    protected function attachRelation($model, $relation, $relationKey)
    {
        $relation = $model->$relation();
        if ($relation instanceof BelongsToMany) {
            $relation->attach($relationKey);
        } else {
            $relationModel = $this->getRelationModel($relation, $relationKey);
            $relation->save($relationModel);
        }
    }

    protected function getRelationModel($relation, $key)
    {
        $relatedModel = $relation->getRelated();
        $relatedKeyName = $relatedModel->getKeyName();
        return $relatedModel->newQuery()->where($relatedKeyName, $key)->first();
    }

    protected function getMetadata($reducer)
    {
        $key = $this->getMetadataKey($reducer);
        return Utils::getPath($this->value, $key, []);
    }

    protected function setMetadata($reducer, $metadata)
    {
        $key = $this->getMetadataKey($reducer);
        $this->value = Utils::setPath($this->value, $key, $metadata);
        return $this;
    }

    protected function getMetadataKey($reducer)
    {
        return sprintf('__relation_reducers.%s', snake_case(class_basename($reducer)));
    }
}
