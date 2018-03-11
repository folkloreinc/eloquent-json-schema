<?php

namespace Folklore\EloquentJsonSchema\Support;

use Illuminate\Contracts\Logging\Log;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Folklore\EloquentJsonSchema\Contracts\HasJsonSchema as HasJsonSchemaContract;
use Folklore\EloquentJsonSchema\Node;

abstract class RelationReducer extends Reducer
{
    abstract protected function getRelationClass($model, $node, $state);

    abstract protected function getRelationSchemaClass($model, $node, $state);

    abstract protected function getRelationSchemaManyClass($model, $node, $state);

    abstract protected function getRelationName($model, $node, $state);

    // @TODO add checks everywhere required
    public function get(HasJsonSchemaContract $model, Node $node, $state)
    {
        if (!$this->shouldUseReducer($model, $node, $state)) {
            return $state;
        }

        $id = Utils::getPath($state, $node->path);
        $relationName = $this->getRelationName($model, $node, $state);
        $value = $this->mutateRelationIdToObject($model, $relationName, $id);

        // Fallback to query if not found in relations and model doesn't exists
        if ($this->shouldQueryRelation($model, $node, $state, $value)) {
            $relationClass = $this->getRelationClass($model, $node, $state);
            $resolvedRelationClass = get_class(app($relationClass));
            $value = $resolvedRelationClass::find($id);
        }

        if ($value !== $id) {
            return Utils::setPath($state, $node->path, $value);
        }

        return $state;
    }

    // @TODO add checks everywhere required
    public function set(HasJsonSchemaContract $model, Node $node, $state)
    {
        if (!$this->shouldUseReducer($model, $node, $state)) {
            return $state;
        }

        $originalValue = Utils::getPath($state, $node->path);
        $relationName = $this->getRelationName($model, $node, $state);
        $value = $this->mutateRelationObjectToId($model, $relationName, $originalValue);

        if ($value !== $originalValue) {
            return Utils::setPath($state, $node->path, $value);
        }

        return $state;
    }

    // @TODO add checks everywhere required
    public function save(HasJsonSchemaContract $model, Node $node, $state)
    {
        if (!$this->shouldUseReducer($model, $node, $state)) {
            return $state;
        }

        $item = Utils::getPath($state, $node->path);
        $relationName = $this->getRelationName($model, $node, $state);
        if (is_null($relationName)) {
            return $state;
        }
        if (!$model->relationLoaded($relationName)) {
            $model->load($relationName);
        }
        $relationSchemaClass = $this->getRelationSchemaClass($model, $node, $state);
        $relationSchemaManyClass = $this->getRelationSchemaManyClass($model, $node, $state);
        if (!is_null($relationSchemaClass) && $node->schema instanceof $relationSchemaClass) {
            $this->updateRelationAtPathWithItem($model, $relationName, $node->path, $item);
        } elseif (!is_null($relationSchemaManyClass) && $node->schema instanceof $relationSchemaManyClass) {
            $this->updateRelationsAtPathWithItems($model, $relationName, $node->path, $item);
        }

        return $state;
    }

    protected function shouldUpdateRelation($model, $relation)
    {
        return false;
    }

    protected function shouldQueryRelation($model, $node, $state, $value)
    {
        $relationName = $this->getRelationName($model, $node, $state);
        $relationClass = $model->{$relationName}();
        return is_null($value) && (
            $model->isSavingJsonSchemas() ||
            $relationClass instanceof HasOneOrMany ||
            !$model->exists ||
            sizeof($model->getDirtyJsonSchemas())
        );
    }

    protected function shouldUseReducer($model, $node, $state)
    {
        if (is_null($state)) {
            return false;
        }

        // Only treat relations matching the associated schema class
        $relationSchemaClass = $this->getRelationSchemaClass($model, $node, $state);
        if (is_null($relationSchemaClass) || !($node->schema instanceof $relationSchemaClass)) {
            return false;
        }

        // Only treat single item nodes, not arrays
        if ($node->schema->getType() !== 'object') {
            return false;
        }

        return true;
    }

    protected function mutateRelationIdToObject($model, $relationName, $id)
    {
        $method = 'mutate'.studly_case($relationName).'RelationIdToObject';
        if (method_exists($this, $method)) {
            return $this->{$method}($model, $relationName, $id);
        }

        if (is_null($id)) {
            return null;
        }

        if (is_object($id)) {
            return $id;
        }

        if (!$model->relationLoaded($relationName)) {
            if (config('json-schema.debug', false)) {
                app(Log::class)->warning(
                    'Relation "'.$relationName.'" is needed for reducer '.get_class($this).' but not explicitly loaded'
                );
            }
            return null;
        }

        $relation = $model->getRelation($relationName);
        return $relation->first(function ($item, $key) use ($relationName, $id) {
            if (!is_object($item)) {
                $item = $key;
            }
            return $this->getRelationIdFromModel($relationName, $item) === (string)$id;
        });
    }

    protected function getRelationIdFromModel($relation, $item)
    {
        $method = 'get'.studly_case($relation).'RelationIdFromModel';
        if (method_exists($this, $method)) {
            return $this->{$method}($relation, $item);
        }

        return isset($item) ? (string)($item->id) : null;
    }

    protected function mutateRelationObjectToId($model, $relation, $object)
    {
        if (is_null($object)) {
            return null;
        }

        if (!is_object($object) && !is_array($object)) {
            return $object;
        }

        $id = $this->getRelationIdFromObject($relation, $object);
        if (is_null($id)) {
            $item = $this->createRelationModelFromObject($model, $relation, $object);
            if (!is_null($item)) {
                $id = $this->getRelationIdFromModel($relation, $item);
            }
        } elseif ($this->shouldUpdateRelation($model, $relation)) {
            $this->updateRelationModelFromObject($model, $relation, $object);
        }
        return $id;
    }

    protected function getRelationIdFromObject($relation, $object)
    {
        $method = 'get'.studly_case($relation).'RelationIdFromObject';
        if (method_exists($this, $method)) {
            return $this->{$method}($relation, $object);
        }

        if (!is_object($object) && !is_array($object)) {
            return $object;
        }

        if (is_array($object)) {
            if (isset($object['id'])) {
                return (string)$object['id'];
            }
            return null;
        }
        return (string)$object->id;
    }

    protected function createRelationModelFromObject($model, $relation, $object)
    {
        $method = 'create'.studly_case($relation).'RelationModelFromObject';
        if (method_exists($this, $method)) {
            return $this->{$method}($model, $relation, $object);
        }

        $relationModel = $model->{$relation}()->getModel();
        $relationModel->fill($object);
        $relationModel->save();
        return $relationModel;
    }

    protected function updateRelationModelFromObject($model, $relation, $object)
    {
        $method = 'update'.studly_case($relation).'RelationModelFromObject';
        if (method_exists($this, $method)) {
            return $this->{$method}($model, $relation, $object);
        }

        $relationId = $this->getRelationIdFromObject($relation, $object);
        if (!is_null($relationId)) {
            $relationModel = $model->{$relation}()->getModel();
            $modelToUpdate = $relationModel::findOrFail($relationId);
            if (!is_null($modelToUpdate)) {
                $modelToUpdate->fill((array)$object);
                $modelToUpdate->save();
            }
        }
    }

    protected function updateRelationAtPathWithItem($model, $relation, $path, $item)
    {
        $method = 'update'.studly_case($relation).'RelationAtPathWithItem';
        if (method_exists($this, $method)) {
            return $this->{$method}($model, $relation, $path, $item);
        }
        $this->detachRelationAtPath($model, $relation, $path);
        if (!is_null($item)) {
            $this->attachRelationAtPath($model, $relation, $path, $item);
        }
    }

    protected function updateRelationsAtPathWithItems($model, $relation, $path, $items)
    {
        $method = 'update'.studly_case($relation).'RelationsAtPathWithItems';
        if (method_exists($this, $method)) {
            return $this->{$method}($model, $relation, $path, $items);
        }

        $this->detachRelationsAtPath($model, $relation, $path, $items);
    }

    protected function detachRelationAtPath($model, $relation, $path)
    {
        $method = 'detach'.studly_case($relation).'RelationByPath';
        if (method_exists($this, $method)) {
            return $this->{$method}($model, $relation, $path);
        }

        $currentItem = $this->getRelationCurrentItemAtPath($model, $relation, $path);
        if ($currentItem) {
            $pathColumn = $this->getRelationPathColumn($relation);
            return $this->detachRelationFromModel($model, $relation, $currentItem, [
                $pathColumn => null,
            ]);
        }
    }

    protected function detachRelationsAtPath($model, $relation, $path)
    {
        $method = 'detach'.studly_case($relation).'RelationsAtPath';
        if (method_exists($this, $method)) {
            return $this->{$method}($model, $relation, $path);
        }

        $pathColumn = $this->getRelationPathColumn($relation);
        $currentItems = $model->{$relation}()->wherePivot($pathColumn, 'like', $path.'%')->get();
        if (!$currentItems->isEmpty()) {
            $pathColumn = $this->getRelationPathColumn($relation);
            $currentItems->each(function ($item) use ($model, $relation, $pathColumn) {
                $this->detachRelationFromModel($model, $relation, $item, [
                    $pathColumn => null,
                ]);
            });
        }
    }

    protected function attachRelationAtPath($model, $relation, $path, $item)
    {
        $method = 'attach'.studly_case($relation).'RelationWithPath';
        if (method_exists($this, $method)) {
            return $this->{$method}($id, $path);
        }

        $pathColumn = $this->getRelationPathColumn($relation);
        return $this->attachRelationToModel($model, $relation, $item, [
            $pathColumn => $path,
        ]);
    }

    protected function attachRelationToModel($model, $relation, $item, $pivot)
    {
        $relationClass = $model->{$relation}();
        if ($relationClass instanceof HasOneOrMany) {
            $method = method_exists($relationClass, 'getForeignKeyName') ?
                'getForeignKeyName' : 'getPlainForeignKey';
            $item->setAttribute($relationClass->$method(), $model->id);
            foreach ($pivot as $column => $value) {
                $item->setAttribute($column, $value);
            }
            $item->save();
            return $item;
        } else {
            return $relationClass->attach($item, $pivot);
        }
    }

    protected function detachRelationFromModel($model, $relation, $item, $pivot)
    {
        $column = $this->getRelationPathColumn($relation);
        $relationClass = $model->{$relation}();
        if ($relationClass instanceof HasOneOrMany) {
            $method = method_exists($relationClass, 'getForeignKeyName') ?
                'getForeignKeyName' : 'getPlainForeignKey';
            $item->setAttribute($relationClass->$method(), null);
            foreach ($pivot as $column => $value) {
                $item->setAttribute($column, $value);
            }
            $item->save();
        } else {
            $model->{$relation}()->detach($item);
        }
    }

    protected function getRelationCurrentItemAtPath($model, $relation, $path)
    {
        if (is_null($path)) {
            return null;
        }

        $pathColumn = $this->getRelationPathColumn($relation);
        $currentItem = $model->{$relation}()->wherePivot($pathColumn, '=', $path)->first();
        return $currentItem;
    }

    protected function getRelationPathColumn($relation)
    {
        $method = 'get'.studly_case($relation).'RelationPathColumn';
        if (method_exists($this, $method)) {
            return $this->{$method}($relation);
        }
        return 'handle';
    }
}
