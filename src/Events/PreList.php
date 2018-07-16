<?php

namespace Laramie\Events;

class PreList
{
    public $model;
    public $query;
    public $user;

    /**
     * Create a new PreList event instance. Listeners **must** be synchronous.
     *
     * This is called from `Laramie\Services\LaramieDataService` _before_
     * executing the query to get a list of items (see `findByType`). It can be
     * used to augment the list query or limit returned items to users with
     * specific roles, etc.
     *
     * @param stdClass                          $model JSON-decoded model definition (from laramie-models.json, etc).
     * @param Illuminate\Database\Query\Builder $query the query that will be used to fetch db instances of the `$model`
     * @param Laramie\Lib\LaramieModel          $user  laramie's version of the logged in user
     */
    public function __construct($model, $query, $user)
    {
        $this->model = $model;
        $this->query = $query;
        $this->user = $user;
    }
}
