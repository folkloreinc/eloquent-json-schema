<?php

namespace Folklore\EloquentJsonSchema\Support;

use LogicException;
use Folklore\EloquentJsonSchema\NodesCollection;
use Folklore\EloquentJsonSchema\JsonSchemaObserver;
use Folklore\EloquentJsonSchema\Contracts\JsonSchemaValidator;
use Folklore\EloquentJsonSchema\ValidationException;
use Folklore\EloquentJsonSchema\Contracts\JsonSchema as JsonSchemaContract;
use Folklore\EloquentJsonSchema\Contracts\Reducer\Get as GetReducer;
use Folklore\EloquentJsonSchema\Contracts\Reducer\Set as SetReducer;
use Folklore\EloquentJsonSchema\Contracts\Reducer\Save as SaveReducer;
use Folklore\EloquentJsonSchema\Contracts\Reducer\Commit as CommitReducer;

trait HasJsonSchema
{
    protected $jsonSchemas = [];

    protected $savingJsonSchemas = false;

    public static function bootHasJsonSchema()
    {
        static::observe(JsonSchemaObserver::class);
    }

    /**
     * Method to create a JsonSchema attribute
     * @param  string|JsonSchemaContract $schema The schema
     * @return JsonSchemaAttribute
     */
    protected function jsonSchema($schema)
    {
        return new JsonSchemaAttribute($this, $schema);
    }

    /**
     * Validate data against JSON Schema
     *
     * @return void
     */
    public function validateJsonSchemaAttributes()
    {
        $validator = app(JsonSchemaValidator::class);
        foreach (array_keys($this->attributes) as $key) {
            if (!$this->attributeHasJsonSchema($key)) {
                continue;
            }
            $schema = $this->getAttributeJsonSchema($key);
            $value = $this->getAttributeValue($key);
            if (!$validator->validateSchema($value, $schema)) {
                throw new ValidationException($validator->getMessages(), $key);
            }
        }
    }

    /**
     * Save JSON Schema
     *
     * @return void
     */
    public function saveJsonSchemaAttributes()
    {
        if ($this->isSavingJsonSchemas()) {
            return;
        }
        $this->setSavingJsonSchemas(true);
        foreach (array_keys($this->attributes) as $key) {
            if ($this->attributeHasJsonSchema($key)) {
                $value = parent::getAttributeValue($key);
                $saveValue = $this->executeJsonSchemaReducers(
                    $key,
                    'save',
                    $value
                );
                $this->executeJsonSchemaReducers($key, 'commit', $saveValue);
            }
        }
        $this->setSavingJsonSchemas(false);
    }

    /**
     * Get a plain attribute (not a relationship).
     *
     * @param  string  $key
     * @return mixed
     */
    public function getAttributeValue($key)
    {
        $value = parent::getAttributeValue($key);
        if ($this->attributeHasJsonSchema($key)) {
            $value = $this->mutateAttributeValueFromJsonSchema($key, $value);
            $value = $this->removeAttributeJsonSchemaMetadata($key, $value);
        }
        return $value;
    }

    /**
     * Set a given attribute on the model.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return $this
     */
    public function setAttribute($key, $value)
    {
        if ($this->attributeHasJsonSchema($key)) {
            $value = $this->ensureAttributeJsonSchemaMetadata($key, $value);
            $value = $this->mutateToAttributeValueFromJsonSchema($key, $value);
        }
        return parent::setAttribute($key, $value);
    }

    /**
     * Mutate an attribute to a json schema value
     *
     * @param  mixed  $value
     * @return array|StdClass
     */
    protected function mutateAttributeValueFromJsonSchema($key, $value)
    {
        return $this->executeJsonSchemaReducers($key, 'get', $value);
    }

    /**
     * Mutate a json schema value to an attribute
     *
     * @param  mixed  $value
     * @param  \Folklore\EloquentJsonSchema\Contracts\JsonSchema  $schema
     * @return string
     */
    protected function mutateToAttributeValueFromJsonSchema($key, $value)
    {
        return $this->mutateJsonSchemaReducers($key, 'set', $value);
    }

    /**
     * Get a json schema value from a method.
     *
     * @param  string  $method
     * @return mixed
     *
     * @throws \LogicException
     */
    protected function getJsonSchemaFromMethod($key)
    {
        $schema = $this->{'get' . Str::studly($key) . 'JsonSchema'}();

        if (!$schema instanceof JsonSchemaContract) {
            throw new LogicException(
                sprintf(
                    '%s::%s must return a JsonSchema instance.',
                    static::class,
                    $method
                )
            );
        }

        return $schema;
    }

    /**
     * Get the JSON Schema Attribute for an attribute
     *
     * @param  string  $key
     * @return \Folklore\EloquentJsonSchema\Support\JsonSchemaAttribute|null
     */
    public function getAttributeJsonSchema($key)
    {
        return $this->getJsonSchemaFromMethod($key);
    }

    /**
     * Determine whether an attribute has a JSON Schema
     *
     * @param  string  $key
     * @return bool
     */
    public function attributeHasJsonSchema($key)
    {
        return method_exists($this, 'get' . Str::studly($key) . 'JsonSchema');
    }

    /**
     * Get the JSON Schema Attribute metadata
     *
     * @param  string  $key
     * @return array
     */
    protected function getAttributeJsonSchemaMetadata($key)
    {
        $value = parent::getAttributeValue($key);
        $namespace = $this->getJsonSchemaMetadataNamespace();
        return !is_null($value) ? data_get($value, $namespace) : null;
    }

    /**
     * Ensure the JSON Schema Attribute metadata follow up
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return array
     */
    protected function ensureAttributeJsonSchemaMetadata($key, $value)
    {
        $metadata = $this->getAttributeJsonSchemaMetadata($key);
        $namespace = $this->getJsonSchemaMetadataNamespace();
        if (is_null($metadata)) {
            return $value;
        }
        data_set($value, $namespace, $metadata);
        return $value;
    }

    /**
     * Remove the JSON Schema Attribute metadata
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return array
     */
    protected function removeAttributeJsonSchemaMetadata($key, $value)
    {
        $namespace = $this->getJsonSchemaMetadataNamespace();
        if (is_array($value) && isset($value[$namespace])) {
            unset($value[$namespace]);
        } elseif (is_object($value) && isset($value->{$namespace})) {
            unset($value->{$namespace});
        }
        return $value;
    }

    /**
     * Set if it's saving json schemas
     *
     * @param  boolean  $saving
     * @return void
     */
    protected function setSavingJsonSchemas($saving)
    {
        $this->savingJsonSchemas = $saving;
        return $this;
    }

    /**
     * Check if it's saving json schemas
     *
     * @return boolean
     */
    public function isSavingJsonSchemas()
    {
        return $this->savingJsonSchemas;
    }

    /**
     * Check the json schema metadata namespace
     *
     * @return string
     */
    public function getJsonSchemaMetadataNamespace()
    {
        return '_json_schema_metadata';
    }

    protected function getJsonSchemaMutatorsFromNode($node)
    {
        $mutators = [];
        if ($node->schema instanceof JsonSchemaContract) {
            $mutators = $node->schema->getMutators();
        }
    }

    protected function mutateWithJsonSchema($key, $value, $method)
    {
        $schema = $this->getAttributeJsonSchema($key);
        $nodes = NodesCollection::makeFromSchema($schema, $value)->setAttribute(
            $key
        );

        return $nodes->reduce(
            function ($value, $node) use ($method) {
                $mutators = $this->getJsonSchemaMutatorsFromNode($node);
                return $mutators->reduce(function ($value, $mutator) use (
                    $method
                ) {
                    return method_exists($mutator, $method)
                        ? $mutator->{$method}($value)
                        : $mutator($value);
                },
                $value);
            },
            is_object($value) ? clone $value : $value
        );
    }

    /**
     * Execute the reducers for a given method
     *
     * @param  string $key
     * @param  string $method
     * @param  mixed $value
     * @return mixed
     */
    protected function executeJsonSchemaReducers($key, $method, $value)
    {
        $interfaces = [
            'get' => GetReducer::class,
            'set' => SetReducer::class,
            'save' => SaveReducer::class,
            'commit' => CommitReducer::class
        ];
        if (!isset($interfaces[$method])) {
            throw new \Exception("Unknown method $method");
        }

        // Get Schema
        $schema = $this->getAttributeJsonSchema($key);

        // Get reducers
        $interface = $interfaces[$method];
        $reducers = collect($schema->getReducers())
            ->map(function ($reducer) {
                return is_string($reducer) ? app($reducer) : $reducer;
            })
            ->filter(function ($reducer) use ($interface) {
                return $reducer instanceof Closure ||
                    $reducer instanceof $interface;
            })
            ->map(function ($reducer) use ($method) {
                return $reducer instanceof Closure
                    ? $reducer
                    : [$reducer, $method];
            });

        $nodes = NodesCollection::makeFromSchema($schema, $value)->setAttribute(
            $key
        );
        $initialValue = is_object($value) ? clone $value : $value;

        // Here we get all nodes from the data and reduce a new value through the reducers.
        // The value is namespaced so in a reducer you get a $node->path prefixed with the
        // attribute name.
        $reducedValue =
            $method === 'commit'
                ? $reducers->reduce(function ($value, $reducer) use (
                    $nodes,
                    $key
                ) {
                    $newValue = call_user_func_array($reducer, [
                        $value,
                        $nodes,
                        $this
                    ]);
                    return $newValue;
                },
                $initialValue)
                : $nodes->reduce(function ($value, $node) use ($reducers) {
                    return $reducers->reduce(function ($value, $reducer) use (
                        $node
                    ) {
                        $newValue = call_user_func_array($reducer, [
                            $value,
                            $node,
                            $this
                        ]);
                        return $newValue;
                    },
                    $value);
                }, $initialValue);

        return $reducedValue;
    }
}
