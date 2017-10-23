<?php

namespace Folklore\EloquentJsonSchema\Support;

use Folklore\EloquentJsonSchema\JsonSchemaObserver;
use Folklore\EloquentJsonSchema\Contracts\JsonSchemaValidator;
use Folklore\EloquentJsonSchema\ValidationException;
use Folklore\EloquentJsonSchema\Contracts\ReducerGetter;
use Folklore\EloquentJsonSchema\Contracts\ReducerSetter;
use Folklore\EloquentJsonSchema\Contracts\ReducerSaver;

trait HasJsonSchema
{
    protected static $defaultJsonSchemas = [];

    protected $enabledJsonSchemasAttributes = [];

    protected $disabledJsonSchemasAttributes = [];

    public static function bootHasJsonSchema()
    {
        static::observe(JsonSchemaObserver::class);
    }

    public static function getGlobalJsonSchemaReducers()
    {
        return config('json-schema.reducers', []);
    }

    public static function getDefaultJsonSchemas()
    {
        return static::$defaultJsonSchemas;
    }

    public static function setDefaultJsonSchemas($schemas)
    {
        static::$defaultJsonSchemas = $schemas;
    }

    public static function setDefaultJsonSchema($key, $schema)
    {
        static::$defaultJsonSchemas[$key] = $schema;
    }

    /**
     * Validate data against JSON Schema
     *
     * @return void
     */
    public function validateJsonSchemaAttributes()
    {
        $validator = app(JsonSchemaValidator::class);
        $attributes = $this->getJsonSchemaAttributes();
        foreach ($attributes as $key) {
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
        $attributes = $this->getJsonSchemaAttributes();
        foreach ($attributes as $key) {
            $value = $this->getAttributeValue($key);
            $this->callJsonSchemaReducers($key, 'save', $value);
        }
    }

    /**
     * Call the reducers for a given method
     *
     * @param  string $key
     * @param  string $method
     * @param  mixed $value
     * @return mixed
     */
    protected function callJsonSchemaReducers($key, $method, $value)
    {
        $interfaces = [
            'get' => ReducerGetter::class,
            'set' => ReducerSetter::class,
            'save' => ReducerSaver::class
        ];
        if (!isset($interfaces[$method])) {
            throw new \Exception("Unknown method $method");
        }
        $interface = $interfaces[$method];

        $schema = $this->getAttributeJsonSchema($key);
        if (is_null($schema)) {
            return $value;
        }

        // Get reducers
        $reducers = array_merge(
            static::getGlobalJsonSchemaReducers(),
            array_where(array_values($this->getJsonSchemaReducers()), function ($reducer) {
                return !is_array($reducer);
            }),
            $schema->getReducers()
        );

        $nodesCollection = $schema->getNodesFromData($value)->prependPath($key);
        $data = [];
        $data[$key] = is_object($value) ? clone $value : $value;
        $data = $nodesCollection->reduce(function ($value, $node) use ($reducers, $interface, $method) {
            foreach ($reducers as $reducer) {
                $reducer = is_string($reducer) ? app($reducer) : $reducer;
                if ($reducer instanceof $interface) {
                    $value = $reducer->{$method}($this, $node, $value);
                } elseif (is_callable($reducer)) {
                    $value = call_user_func_array($reducer, [$this, $node, $value]);
                }
            }
            return $value;
        }, $data);
        return $data[$key];
    }

    /**
     * Cast the given attribute to JSON Schema.
     *
     * @param  mixed  $value
     * @param  \Folklore\EloquentJsonSchema\Contracts\JsonSchema  $schema
     * @return string
     */
    protected function castAttributeAsJsonSchema($key, $value)
    {
        $value = $this->callJsonSchemaReducers($key, 'set', $value);

        if (method_exists($this, 'castAttributeAsJson')) {
            $value = $this->castAttributeAsJson($key, $value);
        } else {
            $value = $this->asJson($value);
        }

        return $value;
    }

    /**
     * Cast an attribute to a native PHP type.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return mixed
     */
    protected function castAttribute($key, $value)
    {
        $type = $this->getCastType($key);
        switch ($type) {
            case 'json_schema':
            case 'json_schema:array':
            case 'json_schema:object':
                $type = explode(':', $type);
                $asObject = sizeof($type) === 2 && $type[1] === 'object';
                $value = $this->fromJson($value, $asObject);
                if ($this->isJsonSchemaAttributeDisabled($key)) {
                    return $value;
                }
                return $this->callJsonSchemaReducers($key, 'get', $value);
            default:
                return $value;
        }
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
        if ($this->hasJsonSchema($key)) {
            $value = $this->castAttributeAsJsonSchema($key, $value);
        }
        return parent::setAttribute($key, $value);
    }

    /**
     * Get the JSON Schema for an attribute
     *
     * @param  string  $key
     * @return \Folklore\EloquentJsonSchema\Contracts\JsonSchema|null
     */
    public function getAttributeJsonSchema($key)
    {
        $schemas = $this->hasJsonSchema($key) ?
            $this->getJsonSchemas() : static::getDefaultJsonSchemas();
        if (!array_key_exists($key, $schemas)) {
            return null;
        }

        $schema = is_string($schemas[$key]) ? app($schemas[$key]) : $schemas[$key];

        // Add the attributes reducers to the schema
        $reducers = $this->getAttributeJsonSchemaReducers($key);
        foreach ($reducers as $reducer) {
            $schema->addReducer($reducer);
        }

        return $schema;
    }

    /**
     * Get the JSON schemas reducers for an attribute.
     *
     * @param  string  $key
     * @return array
     */
    public function getAttributeJsonSchemaReducers($key)
    {
        $reducers = $this->getJsonSchemaReducers();
        return array_key_exists($key, $reducers) ? (array)$reducers[$key] : [];
    }

    /**
     * Get the JSON schemas attributes
     *
     * @return array
     */
    public function getJsonSchemaAttributes()
    {
        return array_reduce(array_keys($this->casts), function($attributes, $key) {
            if (explode(':', $this->getCastType($key))[0] === 'json_schema') {
                $attributes[] = $key;
            }
            return $attributes;
        }, []);
    }

    /**
     * Determine whether an attribute has a JSON Schema
     *
     * @param  string  $key
     * @return bool
     */
    public function hasJsonSchema($key)
    {
        return array_key_exists($key, $this->getJsonSchemas());
    }

    /**
     * Get the JSON schemas array.
     *
     * @return array
     */
    public function getJsonSchemas()
    {
        return isset($this->jsonSchemas) ? $this->jsonSchemas : [];
    }

    /**
     * Set the JSON schemas.
     *
     * @param  array  $schemas
     * @return $this
     */
    public function setJsonSchemas($schemas)
    {
        $this->jsonSchemas = $schemas;
        return $this;
    }

    /**
     * Set the JSON schema of an attribute.
     *
     * @param  string  $key
     * @param  string  $schema
     * @return $this
     */
    public function setJsonSchema($key, $schema)
    {
        if (!isset($this->jsonSchemas)) {
            $this->jsonSchemas = [];
        }

        $this->jsonSchemas[$key] = $schema;
        return $this;
    }

    /**
     * Get the JSON schemas reducers.
     *
     * @return array
     */
    public function getJsonSchemaReducers()
    {
        return isset($this->jsonSchemasReducers) ? $this->jsonSchemasReducers : [];
    }

    /**
     * Set the JSON schemas reducers.
     *
     * @param  array|string  $key
     * @param  array|null  $reducers
     * @return $this
     */
    public function setJsonSchemaReducers($key, $reducers = null)
    {
        if (is_null($reducers)) {
            $this->jsonSchemasReducers = $reducers;
        } else {
            if (!isset($this->jsonSchemasReducers)) {
                $this->jsonSchemasReducers = [];
            }
            $this->jsonSchemasReducers[$key] = $reducers;
        }
        return $this;
    }

    /**
     * Add a JSON schemas reducer.
     *
     * @param  array|string  $key
     * @param  string|callable|null  $reducer
     * @return $this
     */
    public function addJsonSchemaReducer($key, $reducer = null)
    {
        if (!isset($this->jsonSchemasReducers)) {
            $this->jsonSchemasReducers = [];
        }

        if (is_null($reducer)) {
            $this->jsonSchemasReducers[] = $reducer;
        } else {
            if (!array_key_exists($key, $this->jsonSchemasReducers)) {
                $this->jsonSchemasReducers[$key] = [];
            }
            $this->jsonSchemasReducers[$key][] = $reducer;
        }
        return $this;
    }


    public function getDisabledJsonSchemasAttributes()
    {
        return $this->disabledJsonSchemasAttributes;
    }

    public function setDisabledJsonSchemasAttributes(array $disabled)
    {
        $this->disabledJsonSchemasAttributes = $disabled;
        return $this;
    }
    public function addDisabledJsonSchemaAttribute($field = null)
    {
        $this->disabledJsonSchemasAttributes = array_merge(
            $this->disabledJsonSchemasAttributes,
            is_array($field) ? $field : func_get_args()
        );
    }
    public function getEnabledJsonSchemasAttributes()
    {
        return $this->enabledJsonSchemasAttributes;
    }
    public function setEnabledJsonSchemasAttributes(array $enabled)
    {
        $this->enabledJsonSchemasAttributes = $enabled;
        return $this;
    }
    public function addEnabledJsonSchemaAttribute($field = null)
    {
        $this->enabledJsonSchemasAttributes = array_merge(
            $this->enabledJsonSchemasAttributes,
            is_array($field) ? $field : func_get_args()
        );
    }
    public function makeJsonSchemaAttributeEnabled($field)
    {
        $this->disabledJsonSchemasAttributes = array_diff($this->disabledJsonSchemasAttributes, (array) $field);
        if (! empty($this->enabledJsonSchemasAttributes)) {
            $this->addEnabledJsonSchemasAttribute($field);
        }
        return $this;
    }
    public function makeJsonSchemaAttributeDisabled($field)
    {
        $field = (array) $field;
        $this->enabledJsonSchemasAttributes = array_diff($this->enabledJsonSchemasAttributes, $field);
        $this->disabledJsonSchemasAttributes = array_unique(array_merge($this->disabledJsonSchemasAttributes, $field));
        return $this;
    }
    public function disableAllJsonSchemasAttributes()
    {
        $attributes = $schema->getJsonSchemaAttributes();
        $this->makeJsonSchemaAttributeDisabled($attributes);
    }
    public function isJsonSchemaAttributeDisabled($field)
    {
        $disabled = $this->getDisabledJsonSchemasAttributes();
        return in_array($field, $disabled);
    }
}
