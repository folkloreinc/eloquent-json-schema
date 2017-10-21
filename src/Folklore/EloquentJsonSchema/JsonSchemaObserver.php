<?php

namespace Folklore\EloquentJsonSchema;

use Folklore\EloquentJsonSchema\Contracts\JsonSchemaObserver as JsonSchemaObserverContract;
use Folklore\EloquentJsonSchema\Contracts\HasJsonSchema;

class JsonSchemaObserver implements JsonSchemaObserverContract
{
    public function saving(HasJsonSchema $model)
    {
        $model->validateJsonSchemaAttributes();
    }

    public function saved(HasJsonSchema $model)
    {
        $model->saveJsonSchemaAttributes();
    }
}
