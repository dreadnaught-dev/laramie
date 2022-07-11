<?php

declare(strict_types=1);

namespace Laramie\Hooks;

use Illuminate\Foundation\Auth\User;
use Laramie\Lib\LaramieModel;
use Laramie\Lib\ModelSchema;

/*
 * Called before deleting an item. Do additional validation to ensure the item
 * may be deleted, delete dependent items, etc.
 */
class PreDelete
{
    public ModelSchema $model;
    public $item;
    public ?User $user;

    /**
     * Create a new PreDelete hook.
     *
     * @param stdClass                 $model JSON-decoded model definition (from laramie-models.json, etc).
     * @param Laramie\Lib\LaramieModel $item  the db item that will be edited
     * @param Laramie\Lib\LaramieModel $user  laramie's version of the logged in user
     */
    public function __construct(ModelSchema $model, LaramieModel $item, User $user = null)
    {
        $this->model = $model;
        $this->item = $item;
        $this->user = $user;
    }
}
