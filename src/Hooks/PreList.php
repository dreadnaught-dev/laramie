<?php

declare(strict_types=1);

namespace Laramie\Hooks;

use Illuminate\Foundation\Auth\User;
use Laramie\Lib\ModelSpec;

/*
 * Perform logic _before_ data is queried and the admin list page is presented to
 * a user (admin only). For example, you can set a redirect response here if the
 * user doesn't have access to the page, etc.
 */
class PreList
{
    public ModelSpec $model;
    public User $user;
    public $extra;

    /**
     * Create a new PreList hook.
     *
     * @param stdClass                          $model JSON-decoded model definition (from laramie-models.json, etc).
     * @param Illuminate\Database\Query\Builder $query the query that will be used to fetch db instances of the `$model`
     * @param Laramie\Lib\LaramieModel          $user  laramie's version of the logged in user
     */
    public function __construct(ModelSpec $model, User $user, &$extra)
    {
        $this->model = $model;
        $this->user = $user;
        $this->extra = $extra;
    }
}
