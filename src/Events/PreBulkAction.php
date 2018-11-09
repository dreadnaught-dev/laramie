<?php

namespace Laramie\Events;

class PreBulkAction
{
    public $model; // a LaramieModel of the list page
    public $action; // typically "delete", "duplicate", "export", but can be whatever a model has defined.
    public $query; // adjust the query that will be used when performing the bulk action
    public $postData; // contains all filters and ids used on the list page where the bulk action was called
    public $user; // the user making the request
    public $response; // typically don't need to do anything with this

    /**
     * Create a new BulkAction event instance. Listeners must **must** be synchronous.
     *
     * @param stdClass $model   JSON-decoded model definition (from laramie-models.json, etc).
     * @param array    $options provide bulk action handlers additional information (like if all items are selected, which ids are selected, etc)
     */
    public function __construct($model, $nameOfBulkAction, $query, $postData, $user, &$response)
    {
        $this->model = $model;
        $this->nameOfBulkAction = $nameOfBulkAction;
        $this->query = $query;
        $this->postData = $postData;
        $this->user = $user;
        $this->response = $response;
    }
}
