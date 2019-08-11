<?php

use Folklore\EloquentJsonSchema\Support\Model;

class TestChildModel extends Model
{
    protected $table = 'children';

    protected $casts = [
        'data' => 'json'
    ];

    public function data() {
        return $this->jsonSchema(TestChildDataSchema::class);
    }
}
