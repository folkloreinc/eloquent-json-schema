<?php

use Folklore\EloquentJsonSchema\Support\Model;

class TestModel extends Model
{
    protected $table = 'tests';

    protected $casts = [
        'data' => 'json_schema',
    ];

    protected $jsonSchemas = [
        'data' => TestDataSchema::class,
    ];

    public function children() {
        return $this->hasMany(TestChildModel::class, 'test_id');
    }

    public function childrenWithPivot() {
        return $this->belongsToMany(TestChildModel::class, 'tests_children_pivot', 'test_id', 'child_id')
            ->withPivot('handle');
    }
}
