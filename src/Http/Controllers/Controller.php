<?php

namespace Laramie\Http\Controllers;

use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    /**
     * Return an array of all fields that should be shown on the list page (and csv export).
     *
     * @return mixed[] Filter objects
     */
    protected function getListedFields($listableFieldsCollection)
    {
        return $listableFieldsCollection
            ->filter(function ($item) {
                return $item->get('listed');
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

        return collect($model->getFieldsSpecs())
            ->filter(function ($item) {
                return $item->isListable();
            })
            ->each(function ($item) use ($prefs) {
                $item->set('weight', data_get($prefs, $item->getId().'.weight', $item->getWeight()));
                $item->set('listed', data_get($prefs, $item->getId().'.listed', $item->isListByDefault()));
            })
            ->sortBy(function ($item) {
                return $item->getWeight();
            });
    }
}
