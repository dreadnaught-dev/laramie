<?php

namespace Laramie\Events;

use Laramie\Lib\LaramieModel;

class PreDelete
{
    public $model;
    public $item;
    public $user;

    /**
     * Create a new PreDelete event instance. Listeners **must** be synchronous.
     *
     * @param stdClass                 $model JSON-decoded model definition (from laramie-models.json, etc).
     * @param Laramie\Lib\LaramieModel $item  the db item that will be edited
     * @param Laramie\Lib\LaramieModel $user  laramie's version of the logged in user
     */
    public function __construct($model, LaramieModel $item, LaramieModel $user = null)
    {
        $this->model = $model;
        $this->item = $item;
        $this->user = $user;
    }
}
