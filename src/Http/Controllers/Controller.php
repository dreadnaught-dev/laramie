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

    /**
     * Process the request and get an array of filter objects (generally mapped from the query string).
     *
     * @return mixed[] Filter objects
     */
    protected function getFilters($data)
    {
        $filterRegex = '/^filter_(?<filterIndex>[^_]+)_field$/';

        return collect($data)
            ->filter(function ($e, $key) use ($filterRegex) {
                return preg_match($filterRegex, $key) && $e;
            })
            ->map(function ($e, $key) use ($filterRegex, $data) {
                preg_match($filterRegex, $key, $matches);

                return (object) [
                    'key' => $matches['filterIndex'],
                    'field' => data_get($data, sprintf('filter_%s_field', $matches['filterIndex'])),
                    'operation' => data_get($data, sprintf('filter_%s_operation', $matches['filterIndex']), 'is equal to'), // short-hand filter; default to equality check if not specified
                    'value' => data_get($data, sprintf('filter_%s_value', $matches['filterIndex'])),
                ];
            })
            ->values()
            ->all();
    }
}
