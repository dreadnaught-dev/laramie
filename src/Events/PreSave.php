<?php

namespace Laramie\Events;

use Laramie\Lib\LaramieModel;

class PreSave
{
    public $model;
    public $item;
    public $user;

    /**
     * Create a new PreSave event instance. Listeners **must** be synchronous.
     *
     * This is called from `Laramie\Services\LaramieDataService` _before_ an
     * item is saved. It can be used to perform extra validation, enforce
     * workflows, set statuses, etc.
     *
     * @param stdClass                 $model JSON-decoded model definition (from laramie-models.json, etc).
     * @param Laramie\Lib\LaramieModel $item  the db item that was edited
     * @param Laramie\Lib\LaramieModel $user  laramie's version of the logged in user
     */
    public function __construct($model, LaramieModel $item, LaramieModel $user = null)
    {
        $this->model = $model;
        $this->item = $item;
        $this->user = $user;
    }
}
