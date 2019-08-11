<?php

namespace Folklore\EloquentJsonSchema\Support;

use Illuminate\Support\Facades\Log;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Folklore\EloquentJsonSchema\Contracts\HasJsonSchema as HasJsonSchemaContract;
use Folklore\EloquentJsonSchema\Contracts\Reducer\Save;
use Folklore\EloquentJsonSchema\Contracts\Reducer\Commit;
use Folklore\EloquentJsonSchema\Node;
use Folklore\EloquentJsonSchema\NodesCollection;

abstract class RelationReducer extends Reducer implements Save, Commit
{
    /**
     * Get the relation schema class
     * @param \Illuminate\Database\Eloquent\Model $model The current model
     * @param \Folklore\EloquentJsonSchema\Node $node The schema node
     * @param mixed $value The current state
     * @return string
     */
    abstract protected function getRelationSchemaClass($value, $node, $model);

    /**
     * Get the relationship name
     * @param \Illuminate\Database\Eloquent\Model $model The current model
     * @param \Folklore\EloquentJsonSchema\Node $node The schema node
     * @param mixed $value The current state
     * @return string
     */
    abstract protected function getRelationName($value, $node, $model);

    /**
     * Reduce a new value when getting an attribute
     * @param  mixed $value The current value
     * @param  Node $node The current node
     * @param  HasJsonSchemaContract $model The current model
     * @return mixed
     */
    public function get($value, Node $node, HasJsonSchemaContract $model)
    {
        if (!$this->shouldUseReducer($value, $node, $model)) {
            return $value;
        }

        $relationKey = Utils::getPath($value, $node->path);
        $relationName = $this->getRelationName($value, $node, $model);
        $relationModel = $this->mutateRelationKeyToModel(
            $model,
            $relationName,
            $relationKey
        );

        if ($relationModel !== $relationKey) {
            return Utils::setPath($value, $node->path, $relationModel);
        }

        return $value;
    }

    /**
     * Reduce a new value when setting an attribute
     * @param  mixed $value The current value
     * @param  Node $node The current node
     * @param  HasJsonSchemaContract $model The current model
     * @return mixed
     */
    public function set($value, Node $node, HasJsonSchemaContract $model)
    {
        if (!$this->shouldUseReducer($value, $node, $model)) {
            return $value;
        }

        $relationModel = Utils::getPath($value, $node->path);
        $relationName = $this->getRelationName($value, $node, $model);
        $relationKey = $this->mutateRelationModelToKey(
            $model,
            $relationName,
            $relationModel
        );

        if ($relationKey !== $relationModel) {
            return Utils::setPath($value, $node->path, $relationKey);
        }

        return $value;
    }

    /**
     * Reduce a new value when saving a model
     * @param  mixed $value The current value
     * @param  Node $node The current node
     * @param  HasJsonSchemaContract $model The current model
     * @return mixed
     */
    public function save($value, Node $node, HasJsonSchemaContract $model)
    {
        if (!$this->shouldUseReducer($value, $node, $model)) {
            return $value;
        }

        $relationName = $this->getRelationName($value, $node, $model);
        if (!$model->relationLoaded($relationName)) {
            $model->load($relationName);
        }

        $metadata = $this->getPendingMetadata($model, $value);

        $relationKey = Utils::getPath($value, $node->path);
        if (!is_null($relationKey)) {
            if (!isset($metadata[$relationName])) {
                $metadata[$relationName] = [];
            }
            $metadata[$relationName][$node->path] = $relationKey instanceof Model ? $relationKey->getKey() : $relationKey;
        }
        $value = $this->setPendingMetadata($model, $value, $metadata);

        return $value;
    }

    public function commit($value, NodesCollection $collection, HasJsonSchemaContract $model)
    {
        $currentMetadata = $this->getMetadata($model, $value);
        $pendingMetadata = $this->getPendingMetadata($model, $value);
        $relations = array_unique(array_merge(array_keys($currentMetadata), array_keys($pendingMetadata)));
        // dump('COMMIT');
        // dump($value, $currentMetadata, $pendingMetadata, $relations);
        foreach ($relations as $relation) {
            $currentPaths = array_get($currentMetadata, $relation, []);
            $pendingPaths = array_get($pendingMetadata, $relation, []);
            $currentKeys = array_unique(array_values($currentPaths));
            $pendingKeys = array_unique(array_values($pendingPaths));
            $keysToDetach = array_diff($currentKeys, $pendingKeys);
            $keysToAttach = array_diff($pendingKeys, $currentKeys);
            // dump($keysToDetach, $keysToAttach);
            foreach ($keysToDetach as $keyToDetach) {
                $this->detachRelation($model, $relation, $keyToDetach);
            }
            foreach ($keysToAttach as $keyToAttach) {
                $this->attachRelation($model, $relation, $keyToAttach);
            }
        }
        $value = $this->setMetadata($model, $value, $pendingMetadata);
        $value = $this->setPendingMetadata($model, $value, []);
        $model->setAttribute($collection->getAttribute(), $value);
        $model->save();
        return $value;
    }

    protected function detachRelation($model, $relationName, $relationKey)
    {
        $relation = $model->$relationName();
        if ($relation instanceof BelongsToMany) {
            $relation->detach($relationKey);
        } else {
            $relationModel = $this->findRelationFromKey($model, $relationName, $relationKey);
            $relationModel->setAttribute($relation->getForeignKeyName(), null);
            $relationModel->save();
        }
    }

    protected function attachRelation($model, $relationName, $relationKey)
    {
        $relation = $model->$relationName();
        if ($relation instanceof BelongsToMany) {
            $relation->attach($relationKey);
        } else {
            $relationModel = $this->findRelationFromKey($model, $relationName, $relationKey);
            $relation->save($relationModel);
        }
    }

    protected function getMetadata(HasJsonSchemaContract $model, $value)
    {
        $key = $this->getMetadataKey($model);
        return Utils::getPath($value, $key, []);
    }

    protected function setMetadata(HasJsonSchemaContract $model, $value, $metadata)
    {
        $key = $this->getMetadataKey($model);
        return Utils::setPath($value, $key, $metadata);
    }

    protected function getPendingMetadata(HasJsonSchemaContract $model, $value)
    {
        $key = $this->getMetadataKey($model, true);
        return Utils::getPath($value, $key, []);
    }

    protected function setPendingMetadata(HasJsonSchemaContract $model, $value, $metadata)
    {
        $key = $this->getMetadataKey($model, true);
        return Utils::setPath($value, $key, $metadata);
    }

    protected function getMetadataNamespace(HasJsonSchemaContract $model, $pending = false)
    {
        return sprintf('%s.relation_reducers.%s', $model->getJsonSchemaMetadataNamespace(), $pending ? 'pending' : 'current');
    }

    protected function getMetadataKey(HasJsonSchemaContract $model, $pending = false)
    {
        return sprintf('%s.%s', $this->getMetadataNamespace($model, $pending), snake_case(class_basename($this)));
    }

    protected function shouldUpdateRelation($model, $relation)
    {
        return false;
    }

    protected function shouldUseReducer($value, $node, $model)
    {
        if (is_null($value)) {
            return false;
        }

        // Only treat relations matching the associated schema class
        $relationSchemaClass = $this->getRelationSchemaClass(
            $model,
            $node,
            $value
        );
        if (
            is_null($relationSchemaClass) ||
            !($node->schema instanceof $relationSchemaClass)
        ) {
            return false;
        }

        // Only treat single item nodes, not arrays
        if ($node->schema->getType() !== 'object') {
            return false;
        }

        return true;
    }

    protected function mutateRelationKeyToModel(
        $model,
        $relation,
        $relationKey,
        $force = false
    ) {
        $method = sprintf('mutate%sRelationKeyToModel', studly_case($relation));
        if (method_exists($this, $method)) {
            return $this->{$method}($model, $relation, $relationKey);
        }

        if (is_null($relationKey) || $relationKey instanceof Model) {
            return $relationKey;
        }

        if (!$model->exists || $force) {
            return $this->findRelationFromKey($model, $relation, $relationKey);
        }

        if (!$model->relationLoaded($relation)) {
            if (config('json-schema.debug', false)) {
                Log::warning(
                    sprintf(
                        'Relation "%s" is needed for reducer %s but not explicitly loaded',
                        $relation,
                        get_class($this)
                    )
                );
            }
            return null;
        }

        return $model
            ->getRelation($relation)
            ->first(function ($item, $key) use ($relation, $relationKey) {
                return $this->getRelationKeyFromModel($relation, $item) ===
                    (string) $relationKey;
            });
    }

    protected function mutateRelationModelToKey(
        $model,
        $relation,
        $relationModel
    ) {
        if (is_null($relationModel)) {
            return null;
        }

        if (is_numeric($relationModel) || is_string($relationModel)) {
            return (string) $relationModel;
        }

        // prettier-ignore
        if (is_array($relationModel) ||
            (is_object($relationModel) && !($relationModel instanceof Model))
        ) {
            $relatedModel = $this->getRelatedModel($model, $relation);
            $relatedModel->fill(
                $relationModel instanceof Arrayable
                    ? $relationModel->toArray()
                    : (array) $relationModel
            );
            $relationModel = $relatedModel;
        }

        $relationKey = $this->getRelationKeyFromModel(
            $relation,
            $relationModel
        );

        // If relation key doesn't exists, create the relation
        if (is_null($relationKey)) {
            $newModel = $this->createRelationModel(
                $model,
                $relation,
                $relationModel
            );
            if (!is_null($newModel)) {
                $relationKey = $this->getRelationKeyFromModel(
                    $relation,
                    $newModel
                );
            }
        } elseif ($this->shouldUpdateRelation($model, $relation)) {
            $this->updateRelationModel($model, $relation, $relationModel);
        }
        return $relationKey;
    }

    protected function createRelationModel($model, $relation, $relationModel)
    {
        $method = sprintf('create%sRelationModel', studly_case($relation));
        if (method_exists($this, $method)) {
            return $this->{$method}($model, $relation, $relationModel);
        }
        $relationModel->save();
        return $relationModel;
    }

    protected function updateRelationModel($model, $relation, $relationModel)
    {
        $method = sprintf('update%sRelationModel', studly_case($relation));
        if (method_exists($this, $method)) {
            return $this->{$method}($model, $relation, $relationModel);
        }
        $relationModel->save();
        return $relationModel;
    }

    protected function getRelationKeyFromModel($relation, Model $model)
    {
        $method = sprintf('get%sRelationKeyFromModel', studly_case($relation));
        if (method_exists($this, $method)) {
            return $this->{$method}($relation, $model);
        }

        return isset($model) && $model->exists
            ? (string) $model->getKey()
            : null;
    }

    protected function getRelatedModel($model, $relation)
    {
        return $model->$relation()->getRelated();
    }

    protected function findRelationFromKey($model, $relation, $key)
    {
        $relationModel = $this->getRelatedModel($model, $relation);
        $keyName = $relationModel->getKeyName();
        return $relationModel
            ->newQuery()
            ->where($keyName, $key)
            ->first();
    }
}
