<?php

declare(strict_types=1);

namespace Laramie\Hooks;

/*
 * Augment the standard model validator. Add custom fields / properties to the
 * base `model-validator.json` here.  Called from from `Laramie\Lib\ModelLoader`.
 */
class AugmentModelValidator
{
    public $modelValidator;

    public function __construct(mixed $modelValidator)
    {
        $this->modelValidator = $modelValidator;
    }
}
