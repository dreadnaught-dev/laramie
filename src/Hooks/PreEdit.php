<?php

namespace Laramie\Hooks;

use Laramie\Lib\LaramieModel;

/*
 * Called before editing an item in the admin (and only the admin). Can be used
 * to validate user has access to the item, dynamically alter item being edited, etc.
 */
class PreEdit
{
    public $model;
    public $item;
    public $user;

    /**
     * Create a new PreEdit event hook.
     *
     * @param stdClass $model JSON-decoded model definition (from laramie-models.json, etc).
     * @param Laramie\Lib\LaramieModel $item the db item that will be edited
     * @param Laramie\Lib\LaramieModel $user laramie's version of the logged in user
     */
    public function __construct($model, LaramieModel $item, LaramieModel $user = null, &$extra = null)
    {
        $this->model = $model;
        $this->item = $item;
        $this->user = $user;
        $this->extra = $extra;
    }
}
