<?php

namespace Folklore\EloquentJsonSchema;

use JsonSchema\Validator;
use JsonSchema\Constraints\Constraint;
use Illuminate\Contracts\Support\Arrayable;

class JsonSchemaValidator
{
    protected $validator;

    public function __construct()
    {
        $this->validator = new Validator();
    }

    public function validateSchema($value, $schema)
    {
        $valueObject = $value;
        if ($value instanceof Arrayable) {
            $valueObject = $value->toArray();
        } else if ($this->arrayIsAssociative($value)) {
            $valueObject = (object)$value;
        }
        $schemaObject = $schema instanceof Arrayable ? $schema->toArray() : $schema;
        $this->validator->validate($valueObject, $schemaObject, Constraint::CHECK_MODE_APPLY_DEFAULTS);
        return $this->validator->isValid();
    }

    public function validate($attribute, $value, $parameters, $validator)
    {
        if (!sizeof($parameters)) {
            return true;
        }
        $name = $parameters[0];
        $schema = is_string($name) ? app($name) : $name;
        return $this->validateSchema($value, $schema);
    }

    public function getMessages()
    {
        $messages = [];
        foreach ($this->validator->getErrors() as $error) {
            $name = $error['property'];
            if (!isset($messages[$name])) {
                $messages[$name] = [];
            }
            $messages[$name][] = $error['message'];
        }
        return $messages;
    }

    protected function arrayIsAssociative($arr) {
        if (array() === $arr) {
            return false;
        }
        return array_reduce(array_keys($arr), function($isAssociative, $key) {
            return $isAssociative || !is_numeric($key);
        }, false);
    }

    public function __call($method, $parameters)
    {
        if (method_exists($this->validator, $method)) {
            return call_user_func_array([$this->validator, $method], $parameters);
        }
    }
}
