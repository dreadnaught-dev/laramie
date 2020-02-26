<?php

namespace Laramie\Hooks;

/*
 * Augment the standard model validator. Need to add custom fields / properties
 * to the base `model-validator.json`? You can do so here. Called from from `Laramie\Lib\ModelLoader`.
 */
class AugmentModelValidator
{
    public $model;

    /**
     * Create a new AugmentModelValidator event instance.
     *
     * @param stdClass $modelValidator JSON-decoded model validation definition (model-validator.json).
     */
    public function __construct($modelValidator)
    {
        $this->modelValidator = $modelValidator;
    }
}
