<?php

namespace Laramie\Hooks;

use Laramie\Lib\LaramieModel;

/*
 * Called before before displaying a value on the list page or csv export.
 * Allows you to override the default formatting for a field or provide
 * formatting for a custom one.
 *
 * Listeners should return the formatted value.
 */
class FormatDisplayValue
{
    public $dataType;
    public $value;

    /**
     * Create a new FormatDisplayValue hook.
     *
     * @param string $dataType the db item that was edited
     * @param object $value
     */
    public function __construct($dataType, $value)
    {
        $this->dataType = $dataType;
        $this->value = $value;
    }
}
