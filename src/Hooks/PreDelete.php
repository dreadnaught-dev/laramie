<?php

namespace Laramie\Hooks;

use Laramie\Lib\LaramieModel;

/*
 * Called before deleting an item. Do additional validation to ensure the item
 * may be deleted, delete dependent items, etc.
 */
class PreDelete
{
    public $model;
    public $item;
    public $user;

    /**
     * Create a new PreDelete hook.
     *
     * @param stdClass $model JSON-decoded model definition (from laramie-models.json, etc).
     * @param Laramie\Lib\LaramieModel $item the db item that will be edited
     * @param Laramie\Lib\LaramieModel $user laramie's version of the logged in user
     */
    public function __construct($model, LaramieModel $item, LaramieModel $user = null)
    {
        $this->model = $model;
        $this->item = $item;
        $this->user = $user;
    }
}
