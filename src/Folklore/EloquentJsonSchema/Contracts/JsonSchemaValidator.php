<?php

namespace Folklore\EloquentJsonSchema\Contracts;

interface JsonSchemaValidator
{
    public function validate($attribute, $value, $parameters, $validator);

    public function getMessages();
}
