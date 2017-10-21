<?php

namespace Folklore\EloquentJsonSchema;

use \RuntimeException;

class ValidationException extends RuntimeException
{
    protected $schemaErrors;

    public function __construct($schemaErrors, $prefix = null)
    {
        $this->prefix = $prefix;
        $this->schemaErrors = $schemaErrors;
        parent::__construct('Error(s) while validating the schema:'.PHP_EOL.$this->getDetailedMessage($schemaErrors));
    }

    public function getSchemaErrors()
    {
        return $this->schemaErrors;
    }

    protected function getDetailedMessage($schemaErrors)
    {
        $lines = [];
        $prefix = !is_null($this->prefix) ? $this->prefix.'.' : '';
        foreach ($schemaErrors as $key => $value) {
            $messages = (array)$value;
            foreach ($messages as $message) {
                $lines[] = '['.$prefix.$key.']: '.$message;
            }
        }
        return implode(PHP_EOL, $lines);
    }
}
