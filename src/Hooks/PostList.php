<?php

namespace Laramie\Hooks;

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
     *
     * @param stdClass $model JSON-decoded model definition (from laramie-models.json, etc).
     * @param Laramie\Lib\LaramieModel $user laramie's version of the logged in user
     */
    public function __construct($model, &$items, $user, &$extra = null)
    {
        $this->model = $model;
        $this->items = $items;
        $this->user = $user;
        $this->extra = $extra;
    }
}
