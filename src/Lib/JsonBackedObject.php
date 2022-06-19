<?php

declare(strict_types=1);

namespace Laramie\Lib;

abstract class JsonBackedObject
{
    protected object $data;

    public function __construct($jsonObject)
    {
        $this->data = $jsonObject;
    }

    public function toData()
    {
        return $this->data;
    }

    public function get($key, $fallback = null)
    {
        return data_get($this->data, $key, $fallback);
    }

    public function set($key, $value)
    {
        return data_set($this->data, $key, $value);
    }
}
