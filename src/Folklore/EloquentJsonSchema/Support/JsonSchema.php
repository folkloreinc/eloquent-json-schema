<?php

namespace Folklore\EloquentJsonSchema\Support;

use ArrayAccess;
use JsonSerializable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Folklore\EloquentJsonSchema\Contracts\JsonSchema as JsonSchemaContract;
use Folklore\EloquentJsonSchema\Node;
use Folklore\EloquentJsonSchema\NodesCollection;

class JsonSchema implements ArrayAccess, Arrayable, Jsonable, JsonSerializable, JsonSchemaContract
{
    protected $name;
    protected $type = 'object';
    protected $nullable = true;
    protected $properties;
    protected $required;
    protected $default;
    protected $items;
    protected $enum;
    protected $attributes = [];
    protected $reducers;
    protected $schemaAttributes = ['nullable', 'type', 'properties', 'required', 'default', 'items', 'enum', 'appends'];

    public function __construct($schema = [])
    {
        $this->setSchema($schema);
    }

    public function setSchema($schema)
    {
        foreach ($this->schemaAttributes as $attribute) {
            if (isset($schema[$attribute])) {
                $this->{$attribute} = $schema[$attribute];
            }
        }
        $this->attributes = array_except($schema, $this->schemaAttributes);
    }

    public function setName($name)
    {
        $this->name = $name;
        return $this;
    }

    public function getName()
    {
        $class = get_class($this);
        $defaultType = $class !== self::class ? class_basename($class) : $this->type;
        $defaultName = isset($this->name) ? $this->name : $defaultType;
        return method_exists($this, 'name') ? $this->name() : $defaultName;
    }

    public function getAppends()
    {
        $appends = $this->getSchemaAttribute('appends');
        $properties = $this->getProperties();
        if (is_null($properties)) {
            return [];
        }
        if (is_null($appends)) {
            $appends = [];
        } elseif ($appends === '*') {
            $appends = array_keys($properties);
        }

        foreach ($properties as $key => $value) {
            $propertyAppends = $value->getAppends();
            if (sizeof($propertyAppends)) {
                foreach ($propertyAppends as $propertyKey => $propertyValue) {
                    if (is_numeric($propertyKey)) {
                        $appends[$propertyValue] = $key.'.'.$propertyValue;
                    } else {
                        $appends[$propertyKey] = $key.'.'.$propertyValue;
                    }
                }
            }
        }
        return $appends;
    }

    public function getProperties()
    {
        if ($this->getType() !== 'object') {
            return null;
        }

        $properties = $this->getSchemaAttribute('properties');

        if (is_null($properties)) {
            return [];
        }

        $propertiesResolved = [];
        foreach ($properties as $name => $value) {
            if (is_string($value)) {
                $propertiesResolved[$name] = app($value);
            } elseif (is_array($value)) {
                $property = app(JsonSchemaContract::class);
                $property->setSchema($value);
                $propertiesResolved[$name] = $property;
            } else {
                $propertiesResolved[$name] = $value;
            }
        }

        return $propertiesResolved;
    }

    public function setProperties($value)
    {
        return $this->setSchemaAttribute('properties', $value);
    }

    public function addProperty($key, $value)
    {
        if (!isset($this->properties)) {
            $this->properties = [];
        }
        $this->properties[$key] = $value;
        return $this;
    }

    public function getItems()
    {
        if ($this->getType() !== 'array') {
            return null;
        }

        if (method_exists($this, 'items')) {
            $items = $this->items();
        } else {
            $items = isset($this->items) ? $this->items : [];
        }

        if (is_string($items)) {
            return app($items);
        } elseif ($items instanceof JsonSchemaContract || (is_array($items) && isset($items['type']))) {
            return $items;
        }

        $itemsResolved = [];
        foreach ($items as $name => $value) {
            $itemsResolved[$name] = is_string($value) ? app($value) : $value;
        }

        return $itemsResolved;
    }

    public function setItems($value)
    {
        return $this->setSchemaAttribute('items', $value);
    }

    public function getType()
    {
        return $this->getSchemaAttribute('type');
    }

    public function setType($value)
    {
        return $this->setSchemaAttribute('type', $value);
    }

    public function getDefault()
    {
        return $this->getSchemaAttribute('default');
    }

    public function setDefault($value)
    {
        return $this->setSchemaAttribute('default', $value);
    }

    public function getRequired()
    {
        return $this->getSchemaAttribute('required');
    }

    public function setRequired($value)
    {
        return $this->setSchemaAttribute('required', $value);
    }

    public function getEnum()
    {
        return $this->getSchemaAttribute('enum');
    }

    public function setEnum($value)
    {
        return $this->setSchemaAttribute('enum', $value);
    }

    public function getAttributes()
    {
        return $this->getSchemaAttribute('attributes');
    }

    public function setAttributes($value)
    {
        return $this->setSchemaAttribute('attributes', $value);
    }

    public function getReducers()
    {
        return $this->getSchemaAttribute('reducers');
    }

    public function setReducers($value)
    {
        return $this->setSchemaAttribute('reducers', !is_null($value) ? $value : null);
    }

    public function addReducer($value)
    {
        if (!isset($this->reducers)) {
            $this->reducers = [];
        }
        $this->reducers[] = $value;
        return $this;
    }

    protected function getSchemaAttribute($key)
    {
        $value = isset($this->{$key}) ? $this->{$key} : null;
        if (method_exists($this, $key)) {
            if (is_array($value)) {
                $value = array_merge($this->{$key}($value), $value);
            } else {
                $class = class_basename($this);
                $value = $this->{$key}($value);
            }
        }

        return $value;
    }

    protected function setSchemaAttribute($key, $value)
    {
        $this->{$key} = $value;
        return $this;
    }

    public function toArray()
    {
        $nullable = $this->getNullable();
        $type = $this->getType();
        $name = $this->getName();

        // @TODO Add condition to check for array
        $schema = [
            'type' => $nullable ? array_merge(['null'], (array)$type) : $type,
        ];
        if ($name !== $type) {
            $schema['name'] = $name;
        }

        foreach ($this->schemaAttributes as $attribute) {
            if (method_exists($this, 'get'.studly_case($attribute))) {
                $value = $this->{'get'.studly_case($attribute)}();
            } elseif (isset($this->{$attribute})) {
                $value = $this->{$attribute};
            }
            if (isset($value) && !isset($schema[$attribute])) {
                $schema[$attribute] = $value;
            }
        }

        if (isset($schema['properties'])) {
            $properties = $this->getProperties();
            $schema['properties'] = [];
            foreach ($properties as $name => $value) {
                $schema['properties'][$name] = $value instanceof Arrayable ? $value->toArray() : $value;
            }
        }

        if (isset($schema['items'])) {
            $items = $this->getItems();
            $schema['items'] = $items instanceof Arrayable ? $items->toArray() : $items;
        }

        $attributes = $this->getAttributes();

        return array_merge($schema, $attributes);
    }

    public function toObject()
    {
        return json_decode(json_encode($this->toArray()));
    }

    /**
     * Convert the object into something JSON serializable.
     *
     * @return array
     */
    public function jsonSerialize()
    {
        return $this->toArray();
    }

    /**
     * Convert the Fluent instance to JSON.
     *
     * @param  int  $options
     * @return string
     */
    public function toJson($options = 0)
    {
        return json_encode($this->jsonSerialize(), $options);
    }

    /**
     * Determine if the given offset exists.
     *
     * @param  string  $offset
     * @return bool
     */
    public function offsetExists($offset)
    {
        return isset($this->{$offset});
    }

    /**
     * Get the value for a given offset.
     *
     * @param  string  $offset
     * @return mixed
     */
    public function offsetGet($offset)
    {
        return $this->{$offset};
    }

    /**
     * Set the value at the given offset.
     *
     * @param  string  $offset
     * @param  mixed   $value
     * @return void
     */
    public function offsetSet($offset, $value)
    {
        $this->{$offset} = $value;
    }

    /**
     * Unset the value at the given offset.
     *
     * @param  string  $offset
     * @return void
     */
    public function offsetUnset($offset)
    {
        unset($this->{$offset});
    }

    /**
     * Dynamically retrieve the value of an attribute.
     *
     * @param  string  $key
     * @return mixed
     */
    public function __get($key)
    {
        $data = $this->toArray();
        return array_get($data, $key);
    }

    /**
     * Dynamically set the value of an attribute.
     *
     * @param  string  $key
     * @param  mixed   $value
     * @return void
     */
    public function __set($key, $value)
    {
        $this->attributes[$key] = $value;
    }

    /**
     * Dynamically check if an attribute is set.
     *
     * @param  string  $key
     * @return bool
     */
    public function __isset($key)
    {
        return isset($this->attributes[$key]);
    }

    /**
     * Dynamically unset an attribute.
     *
     * @param  string  $key
     * @return void
     */
    public function __unset($key)
    {
        unset($this->attributes[$key]);
    }

    /**
     * Dynamically call schema attributes accessors
     *
     * @param  string  $key
     * @return void
     */
    public function __call($method, $parameters)
    {
        if (preg_match('/^(get|set|with)([A-Z].*)$/i', $method, $matches)) {
            $methodAttribute = Str::snake($matches[2]);
            $foundAttribute = Arr::first($this->schemaAttributes, function ($attribute) use ($methodAttribute) {
                return $methodAttribute === Str::snake($attribute);
            });
            if (!is_null($foundAttribute)) {
                $methodPrefix = $matches[1] === 'with' ? 'get' : $matches[1];
                return $this->{$methodPrefix.'SchemaAttribute'}($foundAttribute);
            }
        }
    }
}
