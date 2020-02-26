<?php

namespace Laramie\Hooks;

/*
 * Dynamically alter models at run time. For effects to take place, the model
 * cache will need to be cleared (via console command or by modifying model defs
 * in json).
 * Called from from `Laramie\Lib\ModelLoader` for each model loaded (including system models).
 */
class ConfigLoaded
{
    public $config;

    /**
     * Create a new ConfigLoaded event instance.
     *
     * @param stdClass $model JSON-decoded model definition (from your models.json).
     */
    public function __construct($config)
    {
        $this->config = $config;
    }
}
