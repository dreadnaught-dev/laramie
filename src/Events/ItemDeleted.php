<?php

namespace Laramie\Events;

use Laramie\Lib\LaramieModel;

class ItemDeleted
{
    public $model;
    public $item;
    public $user;

    /**
     * Create an ItemSaved event instance. Listeners may be asynchronous.
     *
     * @param stdClass $model JSON-decoded model definition (from laramie-models.json, etc).
     * @param Laramie\Lib\LaramieModel $item the db item that was edited
     * @param Laramie\Lib\LaramieModel $user laramie's version of the logged in user
     */
    public function __construct($model, LaramieModel $item, LaramieModel $user = null)
    {
        $this->model = $model;
        $this->item = $item;
        $this->user = $user;
    }
}
