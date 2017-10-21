<?php

namespace Folklore\EloquentJsonSchema\Support;

use StdClass;

class Utils
{
    public static function getPath($data, $path)
    {
        if (empty($path)) {
            return $data;
        }
        return array_reduce(explode('.', $path), function ($value, $key) {
            if (is_null($value) || $key === '*') {
                return $value;
            } elseif (is_object($value)) {
                return isset($value->{$key}) ? $value->{$key} : null;
            } elseif (is_array($value)) {
                return array_get($value, $key);
            }
            return null;
        }, $data);
    }

    public static function setPath($data, $path, $value)
    {
        $segments = explode('.', $path);
        $segment = array_shift($segments);
        if (sizeof($segments)) {
            if (is_object($data) && isset($data->{$segment})) {
                $nextData = $data->{$segment};
            } elseif (is_array($data) && isset($data[$segment])) {
                $nextData = $data[$segment];
            }
            if (!isset($nextData)) {
                $nextData = is_numeric($segments[0]) ? [] : new StdClass();
            }
            $value = self::setPath($nextData, implode('.', $segments), $value);
        }

        if (is_object($data)) {
            $data->{$segment} = $value;
        } elseif (is_array($data)) {
            $data[$segment] = $value;
        }

        return $data;
    }
}
