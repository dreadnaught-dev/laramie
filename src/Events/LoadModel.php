<?php

namespace Laramie\Events;

class LoadModel
{
    public $model;

    /**
     * Create a new LoadModel event instance. Listeners **must** be synchronous.
     *
     * This event is fired from `Laramie\Lib\ModelLoader` for each model
     * while building the cached model json. It can be used to dynamically
     * create model fields, etc.
     *
     * @param stdClass $model JSON-decoded model definition (from laramie-models.json, etc).
     */
    public function __construct($model)
    {
        $this->model = $model;
    }
}
