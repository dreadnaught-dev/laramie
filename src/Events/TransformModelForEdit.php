<?php

namespace Laramie\Events;

use Laramie\Lib\LaramieModel;

class TransformModelForEdit
{
    public $model;
    public $item;
    public $user;

    /**
     * Create a new TransformModelForEdit event instance. Listeners **must** be synchronous.
     *
     * This is called from `Laramie\Http\Controllers\AdminController` when an
     * item is edited. It can be used to dynamically alter the item that will be
     * edited (based on user role, etc), enforce rules on who gets to see what,
     * etc.
     *
     * @param stdClass                 $model JSON-decoded model definition (from laramie-models.json, etc).
     * @param Laramie\Lib\LaramieModel $item  the db item that will be edited
     * @param Laramie\Lib\LaramieModel $user  laramie's version of the logged in user
     */
    public function __construct($model, LaramieModel $item, LaramieModel $user = null, &$extra = null)
    {
        $this->model = $model;
        $this->item = $item;
        $this->user = $user;
        $this->extra = $extra;
    }
}
