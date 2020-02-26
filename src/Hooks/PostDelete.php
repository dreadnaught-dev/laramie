<?php

namespace Laramie\Hooks;

use Laramie\Lib\LaramieModel;

/**
 * Do some work after an item has been deleted.
 */
class PostDelete
{
    public $model;
    public $item;
    public $user;

    /**
     * Create a new PostDelete hook.
     *
     * @param stdClass $model JSON-decoded model definition (from laramie-models.json, etc).
     * @param Laramie\Lib\LaramieModel $item the db item that was deleted
     * @param Laramie\Lib\LaramieModel $user laramie's version of the logged in user
     */
    public function __construct($model, LaramieModel $item, LaramieModel $user = null)
    {
        $this->model = $model;
        $this->item = $item;
        $this->user = $user;
    }
}
