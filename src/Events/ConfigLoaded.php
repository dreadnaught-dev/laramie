<?php

namespace Laramie\Events;

class ConfigLoaded
{
    public $config;

    /**
     * Create a new ConfigLoaded event instance. Listeners **must** be synchronous.
     *
     * This event is fired from `Laramie\Lib\ModelLoader` for each model
     * while building the cached model json. It can be used to dynamically
     * create model fields, etc.
     *
     * @param stdClass $model JSON-decoded model definition (from laramie-models.json, etc).
     */
    public function __construct($config)
    {
        $this->config = $config;
    }
}
