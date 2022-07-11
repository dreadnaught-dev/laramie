<?php

declare(strict_types=1);

namespace Laramie\Hooks;

use Illuminate\Foundation\Auth\User;
use Laramie\Lib\ModelSchema;

/*
 * Alter LaramieModels after they've been fetched from the db, but before
 * they've been returned from the admin list view. Only called from the admin.
 */

class PostList
{
    public ModelSchema $model;
    public $query;
    public User $user;

    /**
     * Create a new PostList hook.
     */
    public function __construct(ModelSchema $model, &$items, User $user, &$extra = null)
    {
        $this->model = $model;
        $this->items = $items;
        $this->user = $user;
        $this->extra = $extra;
    }
}
