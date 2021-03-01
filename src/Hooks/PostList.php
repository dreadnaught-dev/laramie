<?php

namespace Laramie\Hooks;

use Illuminate\Foundation\Auth\User;

/*
 * Alter LaramieModels after they've been fetched from the db, but before
 * they've been returned from the admin list view. Only called from the admin.
 */

class PostList
{
    public $model;
    public $query;
    public $user;

    /**
     * Create a new PostList hook.
     */
    public function __construct($model, &$items, User $user, &$extra = null)
    {
        $this->model = $model;
        $this->items = $items;
        $this->user = $user;
        $this->extra = $extra;
    }
}
