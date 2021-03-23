<?php

namespace Laramie\Http\Controllers;

use DB;
use Illuminate\Http\Request;
use Str;

use Laramie\AdminModels\LaramieAlert;
use Laramie\AdminModels\LaramieComment;
use Laramie\AdminModels\LaramieTag;
use Laramie\Lib\LaramieHelpers;
use Laramie\Lib\ModelSpec;
use Laramie\Services\LaramieDataService;

/**
 * The AjaxController is primarily responsible for handling all the AJAX interactions initiated by the list and edit
 * pages.
 */
class AjaxController extends Controller
{
    protected $dataService;
    protected const MAX_RESULTS = 9e9;

    /**
     * Create a new AjaxController.
     *
     * @param LaramieDataService $dataService Inject the service that talks to the db
     *
     * @return AjaxController
     */
    public function __construct(LaramieDataService $dataService)
    {
        $this->dataService = $dataService;
    }

    /**
     * Return a paginated list of lightly hydrated models.
     *
     * This function takes into account models' aliases and allows for searching base on those aliases, ids, and tags.
     *
     * @return \Illuminate\Http\Response
     */
    public function getList($modelKey, $listModelKey, Request $request, $transformItems = true)
    {
        $outerModel = $this->dataService->getModelByKey($modelKey);
        $model = $this->dataService->getModelByKey($listModelKey);

        $paginator = $this->doSearch($outerModel, $model, $request);

        if ($transformItems) {
            // The name only needs to be unique per reference in case it's a radio select (this value isn't being submitted).
            $name = Str::random(10);
            $alias = $model->getFieldSpec($model->getAlias());

            $paginator->setCollection($paginator->getCollection()
                ->map(function ($e) use ($alias, $name) {
                    return (object) [
                        'id' => $e->id,
                        'name' => $name,
                        'label' => $alias
                            ? LaramieHelpers::formatListValue($alias, data_get($e, $alias->getId()), true)
                            : null,
                        'selected' => data_get($e, 'selected') == 1,
                        'created_at' => \Carbon\Carbon::parse(data_get($e, 'created_at'))->diffForHumans(),
                    ];
                }));
        }

        return $paginator;
    }

    protected function doSearch(ModelSpec $outerModel, ModelSpec $model, Request $request)
    {
        // Ensure that if items are selected, they're valid uuids
        $uuidCollection = collect(preg_split('/\s*[,|]\s*/', $request->get('selectedItems')))
            ->filter(function ($item) {
                return $item && LaramieHelpers::isValidUuid($item);
            });

        // `$outerItemId` refers to the id of the item being edited (may be null).
        $outerItemId = $request->get('itemId');
        if (!LaramieHelpers::isValidUuid($outerItemId)) {
            $outerItemId = null;
        }
        $invertSearch = $request->get('invertSearch') === 'true';

        // `$keywords` is the search string.
        $keywords = $request->get('keywords');

        // `$tag` is optional. If passed, force match. Can pass multiple by separating with a pipe character.
        $tag = $request->get('tag');

        // `$lookupSubtype` refers to what kind of reference field is being searched (image, file, etc).
        $lookupSubtype = $request->get('lookupSubtype');

        $resultsPerPage = $request->get('resultsPerPage');

        $resultsPerPage = is_numeric($resultsPerPage) && $resultsPerPage > -1
            ? (int) $resultsPerPage
            : 10;

        $resultsPerPage = $resultsPerPage === 0 || $resultsPerPage > self::MAX_RESULTS
            ? self::MAX_RESULTS
            : $resultsPerPage;

        $fieldInvokingRequest = $request->get('field');
        $isTypeSpecific = optional($outerModel->getFieldSpec($fieldInvokingRequest))->isTypeSpecific();
        return $this->dataService->findByType(
            $model,
            [
                'source' => 'admin-ajax',
                'outerModelType' => $outerModel->getType(),
                'innerModelType' => $model->getType(),
                'outerItemId' => $outerItemId,
                'resultsPerPage' => $resultsPerPage,
                'isFromAjaxController' => true,
                'isInvertedSearch' => $invertSearch,
            ],
            function ($query) use ($uuidCollection, $keywords, $model, $lookupSubtype, $outerItemId, $outerModel, $fieldInvokingRequest, $isTypeSpecific, $invertSearch, $tag) {
                // Never show the item being edited.
                $query->where('id', '!=', $outerItemId);

                // If there are selected items, add them to the top of the query results (so they don't get lost when
                // searching -- it could be confusing).
                if (count($uuidCollection)) {
                    $uuidSql = $uuidCollection
                        ->map(function ($item) {
                            return sprintf('when \'%s\' then 1', $item);
                        })
                        ->implode(' ');
                    $query->addSelect(\DB::raw('(case id '.$uuidSql.' else 2 end) as selected'));
                    $query->orderBy('selected', 'asc');
                }
                if ($invertSearch) {
                    $query->addSelect(\DB::raw('(case when data->>\''.$fieldInvokingRequest.'\' like \'%'.$outerItemId.'%\' then 1 else 2 end) as selected'));
                    $query->orderBy('selected', 'asc');
                }
                // If searching laramieUploads, limit returned extensions if the subtype is `image`.
                if ($model->getType() == 'laramieUpload' && $lookupSubtype == 'image') {
                    $query->whereRaw(\DB::raw('data->>\'extension\' in (\''.implode("','", config('laramie.allowed_image_types')).'\')'));
                }
                // If a tag was passed, only show items that were tagged accordingly
                if ($tag){
                    $query->whereIn('id', function ($query) use ($tag) {
                        $query->select('laramie_data_id')
                            ->from('laramie_data_meta')
                            ->where('type', '=', 'Tag')
                            ->whereIn(\DB::raw('data->>\'text\''), array_filter(preg_split('/\s*[|]\s*/', $tag)));
                    });
                }
                // Limit by keyword
                $query->where(function ($query) use ($uuidCollection, $keywords, $model) {
                    if ($keywords) {
                        if (count($uuidCollection)) {
                            $query->orWhereIn('id', $uuidCollection->all());
                        }

                        // Search by the model's quickSearch array (will generally be the model's `alias` unless manually set).
                        foreach ($model->getQuickSearch() as $searchFieldName) {
                            // for, we'll also search by id and tags
                            $searchField = $model->getFieldSpec($searchFieldName);

                            // Is the search field is set to an html field? search by whatever the field is pointing to for sorting
                            if ($searchField->getType() === 'html') {
                                if ($searchField->getSortBy() && $model->getFieldSpec($searchField->getSortBy())) {
                                    $searchField = $model->getFieldSpec($searchField->getSortBy());
                                }
                            }

                            if ($searchField->getType() == 'computed') {
                                $query->orWhere(\DB::raw($searchField->getSql()), 'ilike', '%'.$keywords.'%');
                            } elseif ($searchField->getType() == 'markdown') {
                                $query->orWhere(\DB::raw('(data#>>\'{'.$searchField->getFieldName().',markdown}\')'), 'ilike', '%'.$keywords.'%');
                            } elseif (in_array($searchField->getType(), ['id', 'created_at', 'updated_at'])) {
                                $query->orWhere(\DB::raw($searchField->getId()), 'ilike', '%'.$keywords.'%');
                            } else {
                                $query->orWhere(\DB::raw('(data->>\''.$searchField->getFieldName().'\')'), 'ilike', '%'.$keywords.'%');
                            }
                        }

                        // Also search id just in case an item can't be found by its alias (multiple 'James Smiths' for example)
                        if (LaramieHelpers::isValidUuid($keywords)) {
                            $query->orWhere('id', $keywords);
                        }

                        // Lastly search tags
                        $query->orWhereIn('id', function ($query) use ($keywords) {
                            $query->select('laramie_data_id')
                                ->from('laramie_data_meta')
                                ->where('type', '=', 'Tag')
                                ->where(\DB::raw('data->>\'text\''), 'ilike', '%'.$keywords.'%');
                        });
                    }
                });
                // Limit by model type / field.
                if ($isTypeSpecific) {
                    $query->where(\DB::raw('data->>\'source\''), 'ilike', sprintf('%s.%s', $outerModel->getType(), $fieldInvokingRequest));
                }
            }
        );

    }

    /**
     * Return a list of tags / comments for a particular item.
     *
     * @return \Illuminate\Http\Response
     */
    public function getMeta($modelKey, $id)
    {
        return response()->json((object) [
            'tags' => LaramieTag::where('relatedItemId', $id)->orderBy('created_at', 'desc')->get(),
            'comments' => LaramieComment::where('relatedItemId', $id)->orderBy('created_at', 'desc')->get()->map(function($item) { return $item->getAjaxViewModel(); }),
        ]);
    }

    /**
     * Delete a tag / comment.
     *
     * @return \Illuminate\Http\Response
     */
    public function deleteMeta($modelKey, $id, Request $request)
    {
        DB::table('laramie_data')
            ->whereIn('type', ['laramieComment', 'laramieTag'])
            ->where('id', $id)
            ->delete();

        return response()->json((object) ['success' => true]);
    }

    /**
     * Add a tag to an item. Return tags / comments to repopulate ui
     *
     * @return \Illuminate\Http\Response
     */
    public function addTag($modelKey, $id, Request $request)
    {
        LaramieTag::create([
            'relatedItemId' => $id,
            'tag' => $request->get('meta'),
        ]);

        return $this->getMeta($modelKey, $id);
    }

    /**
     * Add a comment to an item. Return all tags / comments after doing so.
     *
     * @return \Illuminate\Http\Response
     */
    public function addComment($modelKey, $id, Request $request)
    {
        LaramieComment::createFromText($id, $request->get('meta'));

        return $this->getMeta($modelKey, $id);
    }

    /**
     * Add a comment to an item. Return all tags / comments after doing so.
     *
     * @return \Illuminate\Http\Response
     */
    public function dismissAlert($id)
    {
        // First, ensure that the the id maps to an alert (don't let this be a vector for arbitrarily deleting items).
        $alert = LaramieAlert::find($id);

        // If the item exists (as an alert), delete it:
        if (data_get($alert, 'id')) {
            $alert->status = 'Read';
            $alert->save();
        }

        // Always return success. Not concerned by failure (either due to an
        // admin manually deleting a record between the user seeing a message and
        // dismissing it, _or_ because the user is attempting to do something
        // naughty, in which case, we don't need to inform them of the reason it
        // failed).
        return response()->json((object) ['success' => true]);
    }

    /**
     * Return the HTML of injected markdown (used by `markdown` fields for preview).
     *
     * @return \Illuminate\Http\Response
     */
    public function markdownToHtml(Request $request)
    {
        return response()->json((object) ['html' => LaramieHelpers::markdownToHtml($request->get('markdown'))]);
    }

    /**
     * Save open/close state of tags/comments and revisions boxes on edit screen.
     *
     * @return \Illuminate\Http\Response
     */
    public function saveEditPrefs(Request $request)
    {
        $prefs = $request->user()->getLaramiePrefs();
        $prefs->hideTags = $request->get('hideTags') == '1';
        $prefs->hideRevisions = $request->get('hideRevisions') == '1';
        $this->dataService->saveUserPrefs($prefs);

        return response()->json((object) ['success' => true]);
    }

    public function modifyRef(Request $request, $modelKey)
    {
        $itemId = $request->get('itemId');
        if (!LaramieHelpers::isValidUuid($itemId)) {
            $itemId = null;
        }
        $item = $this->dataService->findByType($modelKey, null, function($query) use ($itemId) {
                $query->where('id', $itemId)
                    ->limit(1);
            })
            ->first();

        $referenceItemId = $request->get('referenceId');
        if (!LaramieHelpers::isValidUuid($referenceItemId)) {
            $referenceItemId = null;
        }

        if (!$item || !$referenceItemId) {
            abort(403);
        }

        $field = $request->get('field');

        $model = $this->dataService->getModelByKey($modelKey);
        $referenceField = $model->getFieldSpec($field);
        $referenceFieldName = $referenceField->getFieldName();
        $isSelected = $request->get('selected') === '1';

        // TODO -- validate referenceItemId is NOT in the db (new item) or belongs to type: `data_get($referenceField, 'relatedModel')`

        $data = json_decode($item->origData() ?: '{}');

        // Single reference -- only one allowed at a time
        if ($referenceField->getSubtype() === 'single') {
            $data->{$referenceFieldName} = $isSelected
                ? $referenceItemId
                : null;
        } else { // multiple reference, may have many -- we've got to do some book keeping:
            $existingSelection = collect(data_get($data, $referenceFieldName, []));
            $existingSelection->push($referenceItemId);
            $data->{$referenceFieldName} = $existingSelection
                ->filter(function($item) use($isSelected, $referenceItemId) {
                    return $isSelected
                        ? true
                        : $item != $referenceItemId;
                })
                ->unique()
                ->values()
                ->toArray();
        }

        // laramieUsers are a unique case in that they're a proxy for editing
        // data in the `users` table. If we're updating a reference to a
        // laramieUser, we need to remap what we're actually updating.
        $table = $item->type === 'laramieUser' ? 'users' : 'laramie_data';
        $id = $item->type === 'laramieUser' ? $item->user_id : $item->id;

        DB::table($table)
            ->where('id', $id)
            ->update([
                'updated_at' => \Carbon\Carbon::now(config('laramie.timezone'))->toDateTimeString(),
                'data' => json_encode($data),
            ]);

        return response()->json((object) ['success' => true]);
    }
}
