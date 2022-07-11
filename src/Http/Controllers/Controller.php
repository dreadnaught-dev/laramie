<?php

declare(strict_types=1);

namespace Laramie\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    use AuthorizesRequests;
    use DispatchesJobs;
    use ValidatesRequests;

    /**
     * Return an array of all fields that should be shown on the list page (and csv export).
     *
     * @return mixed[] Filter objects
     */
    protected function getListedFields($listableFieldsCollection)
    {
        return $listableFieldsCollection
            ->filter(function ($item) {
                return $item->getIsListed();
            })
            ->all();
    }

    /**
     * Return an array of all fields that _may_ be listed (taking into account saved prefs for this model type).
     *
     * @return mixed[] Field objects
     * @return mixed[] Field objects
     */
    protected function getListableFields($model, $prefs = null)
    {
        $prefs = $prefs !== null ? $prefs : (object) [];

        return collect($model->getFields())
            ->filter(function ($item) {
                return $item->isListable();
            })
            ->each(function ($item) use ($prefs) {
                $item->weight = data_get($prefs, $item->getId().'.weight', $item->getWeight()); // weight is a public property that can be set via json, but may also be overridden via prefs
                $item->setIsListed(data_get($prefs, $item->getId().'.listed', $item->isListByDefault())); // isListed is a run-time property that is determined by prefs.
            })
            ->sortBy(function ($item) {
                return $item->getWeight();
            });
    }
}
