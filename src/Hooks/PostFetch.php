<?php

declare(strict_types=1);

namespace Laramie\Hooks;

use Illuminate\Foundation\Auth\User;
use Laramie\Lib\ModelSchema;

/*
 * Alter LaramieModels after they've been fetched from the db, but before
 * they've been returned from the data service. Similar to PostList, but this is
 * called any time data is fetched, not just from the list page of the admin.
 */
class PostFetch
{
    public ModelSchema $model;
    public $items;
    public ?User $user;
    public $extra;

    /**
     * Create a new PostFetch hook.
     *
     * @param stdClass                 $model JSON-decoded model definition (from laramie-models.json, etc).
     * @param Laramie\Lib\LaramieModel $user  laramie's version of the logged in user
     */
    public function __construct(ModelSchema $model, &$items, User $user = null, &$extra = null)
    {
        $this->model = $model;
        $this->items = $items;
        $this->user = $user;
        $this->extra = $extra;
    }
}
