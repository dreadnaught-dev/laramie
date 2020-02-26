<?php

namespace Laramie\Hooks;

/*
 * Alter the query used when fetching items. Unless events are explicitly turned
 * off, this is called any time data is fetched (single items / multiple items).
 * Can be used to filter the query based on user attributes before data is
 * received from the db (e.g., limit data based on user roles, etc).
 */
class FilterQuery
{
    public $model;
    public $query;
    public $user;
    public $extra;

    /**
     * Create a new FilterQuery hook.
     *
     * @param stdClass $model JSON-decoded model definition (from laramie-models.json, etc).
     * @param Illuminate\Database\Query\Builder $query the query that will be used to fetch db instances of the `$model`
     * @param Laramie\Lib\LaramieModel $user laramie's version of the logged in user
     */
    public function __construct($model, $query, $user, &$extra = null)
    {
        $this->model = $model;
        $this->query = $query;
        $this->user = $user;
        $this->extra = $extra;
    }
}
