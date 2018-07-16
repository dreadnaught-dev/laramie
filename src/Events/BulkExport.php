<?php

namespace Laramie\Events;

class BulkExport extends BulkAction
{
    public $listableFields;
    public $outputFile;

    /**
     * Create a BullkExport event instance. Listeners **must** be synchronous.
     *
     * See `\Laramie\Events\BulkAction`
     *
     * @param stdClass $model          JSON-decoded model definition (from laramie-models.json, etc).
     * @param array    $options        provide bulk action handlers additional information (like if all items are selected, which ids are selected, etc)
     * @param array    $listableFields list of fields that should be included in the export
     * @param string   $outputFile     path of the file to save the export to
     */
    public function __construct($model, $options, $listableFields, $outputFile)
    {
        $this->model = $model;
        $this->options = $options;
        $this->listableFields = $listableFields;
        $this->outputFile = $outputFile;
    }
}
