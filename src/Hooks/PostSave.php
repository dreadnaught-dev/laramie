<?php

namespace Laramie\Hooks;

use Illuminate\Foundation\Auth\User;

use Laramie\Lib\LaramieModel;
use Laramie\Lib\ModelSpec;

/*
 * Do some work _after_ saving an item. Exceptions thrown in this hook will be
 * caught when saving from the admin (rolling back a transaction). Outside of the
 * admin, that handling is left up to the implementor.
 *
 * This is a synchronous _hook_. So while you can perform post-save actions
 * here (like sending email, etc), any long-running post-save tasks should be
 * handled by async events
 */
class PostSave
{
    public ModelSpec $model;
    public $item;
    public ?User $user;

    /**
     * Create a PostSave hook.
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
