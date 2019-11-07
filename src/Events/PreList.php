<?php

namespace Laramie\Events;

class PreList
{
    public $model;
    public $user;
    public $extra;

    /**
     * Create a new PreList event instance. Listeners **must** be synchronous.
     *
     * This is called from the AdminController _before_ items are fetched. If a
     * `response` attribute is added to `$extra`, the list page will return that
     * instead of its normal response.
     *
     * @param stdClass                          $model JSON-decoded model definition (from laramie-models.json, etc).
     * @param Illuminate\Database\Query\Builder $query the query that will be used to fetch db instances of the `$model`
     * @param Laramie\Lib\LaramieModel          $user  laramie's version of the logged in user
     */
    public function __construct($model, $user, &$extra)
    {
        $this->model = $model;
        $this->user = $user;
        $this->extra = $extra;
    }
}
