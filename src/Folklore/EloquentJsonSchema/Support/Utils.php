<?php

namespace Folklore\EloquentJsonSchema\Support;

use StdClass;

class Utils
{
    public static function getPath($data, $path, $default = null)
    {
        if (empty($path)) {
            return $data;
        }
        return data_get($data, $path, $default);
    }

    public static function setPath(&$data, $path, $value)
    {
        return data_set($data, $path, $value);
    }
}
