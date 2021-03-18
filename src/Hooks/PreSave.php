<?php

namespace Laramie\Hooks;

use Illuminate\Foundation\Auth\User;

use Laramie\Lib\ModelSpec;
use Laramie\Lib\LaramieModel;

/*
 * Called before persisting an item. It can be used to perform extra
 * validation, set additional attributes (e.g., statuses), etc.
 * Throwing an error within the hook will prevent the save from completing.
 */
class PreSave
{
    public ModelSpec $model;
    public $item;
    public ?User $user;

    /**
     * Create a new PreSave hook.
     *
     * @param stdClass $model JSON-decoded model definition (from laramie-models.json, etc).
     * @param Laramie\Lib\LaramieModel $item the db item that was edited
     * @param Laramie\Lib\LaramieModel $user laramie's version of the logged in user
     */
    public function __construct(ModelSpec $model, LaramieModel $item, User $user = null)
    {
        $this->model = $model;
        $this->item = $item;
        $this->user = $user;
    }
}
