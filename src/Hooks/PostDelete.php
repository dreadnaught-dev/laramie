<?php

declare(strict_types=1);

namespace Laramie\Hooks;

use Illuminate\Foundation\Auth\User;
use Laramie\Lib\LaramieModel;
use Laramie\Lib\ModelSpec;

/**
 * Do some work after an item has been deleted.
 */
class PostDelete
{
    public ModelSpec $model;
    public $item;
    public ?User $user;

    /**
     * Create a new PostDelete hook.
     *
     * @param stdClass                 $model JSON-decoded model definition (from laramie-models.json, etc).
     * @param Laramie\Lib\LaramieModel $item  the db item that was deleted
     * @param Laramie\Lib\LaramieModel $user  laramie's version of the logged in user
     */
    public function __construct(ModelSpec $model, LaramieModel $item, User $user = null)
    {
        $this->model = $model;
        $this->item = $item;
        $this->user = $user;
    }
}
