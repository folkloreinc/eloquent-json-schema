<?php

namespace Folklore\EloquentJsonSchema\Contracts;

interface JsonSchemaObserver
{
    public function saving(HasJsonSchema $model);

    public function saved(HasJsonSchema $model);
}
