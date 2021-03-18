<?php

namespace Laramie\Hooks;

/*
 * Augment the standard model validator. Add custom fields / properties to the
 * base `model-validator.json` here.  Called from from `Laramie\Lib\ModelLoader`.
 */
class AugmentModelValidator
{
    public $modelValidator;

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
