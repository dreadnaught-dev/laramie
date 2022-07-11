<?php

declare(strict_types=1);

namespace Laramie\Hooks;

use Illuminate\Foundation\Auth\User;
use Laramie\Lib\LaramieModel;
use Laramie\Lib\ModelSchema;

/*
 * Dynamically alter an modal/item that will be edited (based on user role, etc).
 * Called from `Laramie\Http\Controllers\AdminController` when an item is edited.
 */
class TransformModelForEdit
{
    public ModelSchema $model;
    public LaramieModel $item;
    public ?User $user;

    /**
     * Create a new TransformModelForEdit hook.
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
