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
                return $item->listed;
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
            ->filter(function ($item) use($prefs) {
                return $item->isListable;
            })
            ->each(function ($item) use ($prefs) {
                $item->weight = data_get($prefs, $item->id.'.weight', $item->weight);
                $item->listed = data_get($prefs, $item->id.'.listed', $item->listByDefault);
            })
            ->sortBy(function ($item) {
                return $item->weight;
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
            ->filter(function ($item, $key) use ($filterRegex) {
                return preg_match($filterRegex, $key) && $item;
            })
            ->map(function ($item, $key) use ($filterRegex, $data) {
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
