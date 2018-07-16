<?php

namespace Laramie\Events;

class BulkAction
{
    public $model;
    public $options;

    /**
     * Create a new BulkAction event instance. Listeners must **must** be synchronous.
     *
     * @param stdClass $model   JSON-decoded model definition (from laramie-models.json, etc).
     * @param array    $options provide bulk action handlers additional information (like if all items are selected, which ids are selected, etc)
     */
    public function __construct($model, $options)
    {
        $this->model = $model;
        $this->options = $options;
    }
}
