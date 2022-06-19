<?php

declare(strict_types=1);

namespace Laramie\Hooks;

use Illuminate\Foundation\Auth\User;
use Laramie\Lib\ModelSpec;

/*
 * Alter the query used when fetching items. Unless events are explicitly turned
 * off, this is called any time data is fetched (single items / multiple items).
 * Can be used to filter the query based on user attributes before data is
 * received from the db (e.g., limit data based on user roles, etc).
 */
class FilterQuery
{
    public ModelSpec $model;
    public $query;
    public ?User $user;
    public $extra;

    /**
     * Create a new FilterQuery hook.
     *
     * @param stdClass                          $model JSON-decoded model definition (from laramie-models.json, etc).
     * @param Illuminate\Database\Query\Builder $query the query that will be used to fetch db instances of the `$model`
     * @param Laramie\Lib\LaramieModel          $user  laramie's version of the logged in user
     */
    public function __construct(ModelSpec $model, $query, User $user = null, &$extra = null)
    {
        $this->model = $model;
        $this->query = $query;
        $this->user = $user;
        $this->extra = $extra;
    }
}
