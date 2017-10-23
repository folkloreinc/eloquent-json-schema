<?php

use Folklore\EloquentJsonSchema\Support\Model;

class TestChildModel extends Model
{
    protected $table = 'children';

    protected $casts = [
        'data' => 'array:json_schema',
    ];

    protected $jsonSchemas = [
        'data' => TestChildDataSchema::class,
    ];
}
