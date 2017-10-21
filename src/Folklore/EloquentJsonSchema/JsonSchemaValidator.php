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
        $valueObject = (object)($value instanceof Arrayable ? $value->toArray() : $value);
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
        $namespace = array_get($parameters, 1, null);
        $schema = app('panneau')->schema($name, $namespace);
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

    public function __call($method, $parameters)
    {
        if (method_exists($this->validator, $method)) {
            return call_user_func_array([$this->validator, $method], $parameters);
        }
    }
}
