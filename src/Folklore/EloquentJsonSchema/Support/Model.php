<?php

namespace Folklore\EloquentJsonSchema\Support;

use Illuminate\Database\Eloquent\Model as BaseModel;
use Folklore\EloquentJsonSchema\Contracts\HasJsonSchema as HasJsonSchemaContract;
use Folklore\EloquentJsonSchema\Support\HasJsonSchema as HasJsonSchemaTrait;

class Model extends BaseModel implements HasJsonSchemaContract
{
    use HasJsonSchemaTrait;
}
