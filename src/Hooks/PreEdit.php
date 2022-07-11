<?php

declare(strict_types=1);

namespace Laramie\Hooks;

use Illuminate\Foundation\Auth\User;
use Laramie\Lib\LaramieModel;
use Laramie\Lib\ModelSchema;

/*
 * Called before editing an item in the admin (and only the admin). Can be used
 * to validate user has access to the item, dynamically alter item being edited, etc.
 */
class PreEdit
{
    public ModelSchema $model;
    public $item;
    public ?User $user;

    /**
     * Create a new PreEdit event hook.
     *
     * @param stdClass                 $model JSON-decoded model definition (from laramie-models.json, etc).
     * @param Laramie\Lib\LaramieModel $item  the db item that will be edited
     * @param Laramie\Lib\LaramieModel $user  laramie's version of the logged in user
     */
    public function __construct(ModelSchema $model, LaramieModel $item, User $user = null, &$extra = null)
    {
        $this->model = $model;
        $this->item = $item;
        $this->user = $user;
        $this->extra = $extra;
    }
}
