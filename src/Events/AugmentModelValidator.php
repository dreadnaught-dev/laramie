<?php

namespace Laramie\Events;

class AugmentModelValidator
{
    public $model;

    /**
     * Create a new AugmentModelValidator event instance. Listeners **must** be synchronous.
     *
     * This event is fired from `Laramie\Lib\ModelLoader`.  It can be used by
     * modules to augment the standard validation model (for loading).
     *
     * @param stdClass $modelValidator JSON-decoded model validation definition (from model-validator.json).
     */
    public function __construct($modelValidator)
    {
        $this->modelValidator = $modelValidator;
    }
}
