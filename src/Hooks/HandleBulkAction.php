<?php

namespace Laramie\Hooks;

use Illuminate\Foundation\Auth\User;

/*
 * The event actually does the work of handling a bulk action request.
 */
class HandleBulkAction
{
    public $model; // LaramieModel of the list page initiating the bulk action
    public $action; // typically "delete", "duplicate", "export", but can be whatever a model has defined.
    public $items; // query that will be used when performing the bulk action
    public $user; // the user making the request
    public $extra; // allows bulk action to pass messages back to the caller (e.g., a custom response for exporting data, etc. Currently only `response` is checked).

    /**
     * Create a new HandleBulkAction hook.
     */
    public function __construct($model, $nameOfBulkAction, $items, User $user, &$extra)
    {
        $this->model = $model;
        $this->nameOfBulkAction = $nameOfBulkAction;
        $this->items = $items;
        $this->user = $user;
        $this->extra = $extra;
    }
}
