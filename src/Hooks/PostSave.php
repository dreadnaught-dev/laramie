<?php

namespace Laramie\Hooks;

use Laramie\Lib\LaramieModel;

/*
 * Do some work _after_ saving an item. Exceptions thrown in this hook will be
 * caught when saving from the admin (rolling back a transaction). Outside of the
 * admin, that handling is left up to the implementor.
 *
 * This is a synchronous _hook_. So while you can perform post-save actions
 * here (like sending email, etc), any long-running post-save tasks should be
 * dispatched to an async job.
 */
class PostSave
{
    public $model;
    public $item;
    public $user;

    /**
     * Create a PostSave hook.
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
