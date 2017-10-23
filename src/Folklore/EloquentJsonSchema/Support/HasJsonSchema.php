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

        // Here we get all nodes from the data and reduce a new value through the reducers.
        // The value is namespaced so in a reducer you get a $node->path prefixed with the
        // attribute name.
        $nodesCollection = $schema->getNodesFromData($value)
            ->prependPath($key);
        $namespacedValue = [
            $key => is_object($value) ? clone $value : $value
        ];
        $reducedValue = $nodesCollection->reduce(function ($value, $node) use ($reducers, $interface, $method) {
            foreach ($reducers as $reducer) {
                $reducer = is_string($reducer) ? app($reducer) : $reducer;
                if ($reducer instanceof $interface) {
                    $value = $reducer->{$method}($this, $node, $value);
                } elseif (is_callable($reducer)) {
                    $value = call_user_func_array($reducer, [$this, $node, $value]);
                }
            }
            return $value;
        }, $namespacedValue);
        return $reducedValue[$key];
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

    /**
     * Get disabled attributes
     *
     * @return array
     */
    public function getDisabledJsonSchemasAttributes()
    {
        return $this->disabledJsonSchemasAttributes;
    }

    /**
     * Set disabled attributes
     *
     * @param  array  $disabled
     * @return array
     */
    public function setDisabledJsonSchemasAttributes(array $disabled)
    {
        $this->disabledJsonSchemasAttributes = $disabled;
        return $this;
    }

    /**
     * Add a disabled attribute
     *
     * @param  string|null  $attribute
     * @return array
     */
    public function addDisabledJsonSchemaAttribute($attribute = null)
    {
        $this->disabledJsonSchemasAttributes = array_merge(
            $this->disabledJsonSchemasAttributes,
            is_array($attribute) ? $attribute : func_get_args()
        );
    }

    /**
     * Get enabled attributes
     *
     * @return array
     */
    public function getEnabledJsonSchemasAttributes()
    {
        return $this->enabledJsonSchemasAttributes;
    }

    /**
     * Set enabled attributes
     *
     * @param  array  $enabled
     * @return array
     */
    public function setEnabledJsonSchemasAttributes(array $enabled)
    {
        $this->enabledJsonSchemasAttributes = $enabled;
        return $this;
    }

    /**
     * Add a enabled attribute
     *
     * @param  string|null  $attribute
     * @return array
     */
    public function addEnabledJsonSchemaAttribute($attribute = null)
    {
        $this->enabledJsonSchemasAttributes = array_merge(
            $this->enabledJsonSchemasAttributes,
            is_array($attribute) ? $attribute : func_get_args()
        );
    }

    /**
     * Make attribute enabled
     *
     * @param  string  $attribute
     * @return array
     */
    public function makeJsonSchemaAttributeEnabled($attribute)
    {
        $this->disabledJsonSchemasAttributes = array_diff(
            $this->disabledJsonSchemasAttributes,
            (array) $attribute
        );
        if (! empty($this->enabledJsonSchemasAttributes)) {
            $this->addEnabledJsonSchemasAttribute($attribute);
        }
        return $this;
    }

    /**
     * Make attribute disabled
     *
     * @param  string  $attribute
     * @return array
     */
    public function makeJsonSchemaAttributeDisabled($attribute)
    {
        $attribute = (array)$attribute;
        $this->enabledJsonSchemasAttributes = array_diff(
            $this->enabledJsonSchemasAttributes,
            $attribute
        );
        $this->disabledJsonSchemasAttributes = array_unique(array_merge(
            $this->disabledJsonSchemasAttributes,
            $attribute
        ));
        return $this;
    }

    /**
     * Disabled all attributes
     *
     * @return void
     */
    public function disableAllJsonSchemasAttributes()
    {
        $attributes = $schema->getJsonSchemaAttributes();
        $this->makeJsonSchemaAttributeDisabled($attributes);
    }

    /**
     * Check if an attribute is disabled
     *
     * @param  string  $attribute
     * @return boolean
     */
    public function isJsonSchemaAttributeDisabled($attribute)
    {
        $disabled = $this->getDisabledJsonSchemasAttributes();
        return in_array($attribute, $disabled);
    }
}
