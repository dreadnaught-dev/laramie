<?php

namespace Laramie\Events;

class PostList
{
    public $model;
    public $query;
    public $user;

    /**
     * Create a new PostList event instance. Listeners **must** be synchronous.
     *
     * This is called from `Laramie\Services\LaramieDataService` after fetching
     * items from the db, but _before_ returning them from `findByType`
     *
     * @param stdClass                          $model JSON-decoded model definition (from laramie-models.json, etc).
     * @param Laramie\Lib\LaramieModel          $user  laramie's version of the logged in user
     */
    public function __construct($model, &$items, $user, &$extra = null)
    {
        $this->model = $model;
        $this->items = $items;
        $this->user = $user;
        $this->extra = $extra;
    }
}
