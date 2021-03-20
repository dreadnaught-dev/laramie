<?php

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

    protected function get($key, $fallback = null)
    {
        return data_get($this->data, $key, $fallback);
    }
}
