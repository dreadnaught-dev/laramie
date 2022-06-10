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
            ->filter(function ($e) {
                return $e->listed;
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

        return collect(object_get($model, 'fields', (object) []))
            ->filter(function ($e) use($prefs) {
                // If there are prefs set for the user for the model, but they are missing a field, interpret that as not listed.
                if (count((array)$prefs) > 0 && data_get($prefs, $e->_fieldName) === null) {
                    return false;
                }

                return $e->isListable;
            })
            ->each(function ($e) use ($prefs) {
                $e->weight = object_get($prefs, $e->id.'.weight', $e->weight);
                $e->listed = object_get($prefs, $e->id.'.listed', $e->listByDefault);
            })
            ->sortBy(function ($e) {
                return $e->weight;
            });
    }
}
