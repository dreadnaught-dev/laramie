<?php

namespace Laramie\Events;

use Laramie\Lib\LaramieModel;

class PostSave
{
    public $model;
    public $item;
    public $user;

    /**
     * Create a PostSave event instance. Listeners _may_ be asynchronous.
     *
     * This is called from `Laramie\Services\LaramieDataService` _after_ an
     * item is saved. It can be used to perform post-save actions like sending
     * emails, setting workflow statuses, etc.
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
