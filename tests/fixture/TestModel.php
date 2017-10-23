<?php

use Folklore\EloquentJsonSchema\Support\Model;

class TestModel extends Model
{
    protected $table = 'tests';

    protected $casts = [
        'data' => 'array:json_schema',
    ];

    protected $jsonSchemas = [
        'data' => TestDataSchema::class,
    ];

    public function children() {
        return $this->hasMany(TestChildModel::class, 'test_id');
    }
}
