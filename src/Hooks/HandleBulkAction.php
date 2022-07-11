<?php

declare(strict_types=1);

namespace Laramie\Hooks;

use Illuminate\Foundation\Auth\User;
use Laramie\Lib\ModelSchema;

/*
 * The event actually does the work of handling a bulk action request.
 */
class HandleBulkAction
{
    public ModelSchema $model;
    public $action; // typically "delete", "duplicate", "export", but can be whatever a model has defined.
    public $items; // query that will be used when performing the bulk action
    public User $user; // the user making the request
    public $extra; // allows bulk action to pass messages back to the caller (e.g., a custom response for exporting data, etc. Currently only `response` is checked).

    /**
     * Create a new HandleBulkAction hook.
     */
    public function __construct(ModelSchema $model, $nameOfBulkAction, $items, User $user, &$extra)
    {
        $this->model = $model;
        $this->nameOfBulkAction = $nameOfBulkAction;
        $this->items = $items;
        $this->user = $user;
        $this->extra = $extra;
    }
}
