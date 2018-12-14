<?php

namespace Laramie\Events;

class BulkAction
{
    public $model; // LaramieModel of the list page initiating the bulk action
    public $action; // typically "delete", "duplicate", "export", but can be whatever a model has defined.
    public $query; // query that will be used when performing the bulk action
    public $postData; // contains all filters and ids used on the list page where the bulk action was called
    public $user; // the user making the request
    public $extra; // allows bulk action to pass messages back to the caller (e.g., a custom response for exporting data, etc. Currently only `response` is checked).

    /**
     * Create a new BulkAction event. Listeners must **must** be synchronous.
     * This event actually does the work of the bulk action.
     */
    public function __construct($model, $nameOfBulkAction, $query, $postData, $user, &$extra)
    {
        $this->model = $model;
        $this->nameOfBulkAction = $nameOfBulkAction;
        $this->query = $query;
        $this->postData = $postData;
        $this->user = $user;
        $this->extra = $extra;
    }
}
