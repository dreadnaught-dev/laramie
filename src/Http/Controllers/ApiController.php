<?php

declare(strict_types=1);

namespace Laramie\Http\Controllers;

use DB;
use Illuminate\Http\Request;
use Laramie\Lib\LaramieHelpers;
use Laramie\Services\LaramieDataService;

/**
 * The ApiController provides a 'Contentful'-like API to your data (read-only for now).
 */
class ApiController extends Controller
{
    protected $dataService;
    private $validationRules = [];

    /**
     * Create a new ApiController.
     *
     * @param LaramieDataService $dataService Inject the service that talks to the db
     *
     * @return ApiController
     */
    public function __construct(LaramieDataService $dataService)
    {
        $this->dataService = $dataService;
    }

    /**
     * Return the list page for a model type.
     *
     * Applies sorting and filters similar to the list page.
     *
     * @return \Illuminate\Http\Response
     */
    public function getList(Request $request, $modelKey, LaramieHelpers $viewHelper)
    {
        $model = $this->dataService->getModelByKey($modelKey);

        $options = $request->all();

        $filters = LaramieHelpers::extractFiltersFromData($options);

        $options['filters'] = $filters;
        $options['quickSearch'] = $request->get('quick-search');
        $options['sortDirection'] = $request->get('sort-direction');

        $items = $this->dataService->findByType($model, $options);

        return response()->json($items);
    }

    /**
     * Return the detail view for a particular item.
     *
     * @return \Illuminate\Http\Response
     */
    public function getItem(Request $request, $modelKey, $id)
    {
        $depth = (int) $request->get('depth', '0');

        $depth = min($depth, 5);

        $item = $this->dataService->findById($this->dataService->getModelByKey($modelKey), $id, (int) $depth);

        return response()->json($item);
    }
}
