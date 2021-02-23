<?php

namespace Laramie\Http\Controllers;

use DB;
use Illuminate\Http\Request;
use Laramie\Lib\LaramieHelpers;
use Laramie\Services\LaramieDataService;
use Ramsey\Uuid\Uuid;
use Str;

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
            $alias = data_get($model, 'fields.'.$model->alias);

            $paginator->setCollection($paginator->getCollection()
                ->map(function ($e) use ($alias, $name) {
                    return (object) [
                        'id' => $e->id,
                        'name' => $name,
                        'label' => $alias
                            ? LaramieHelpers::formatListValue($alias, data_get($e, $alias->id), true)
                            : null,
                        'selected' => data_get($e, 'selected') == 1,
                        'created_at' => \Carbon\Carbon::parse(data_get($e, 'created_at'), config('laramie.timezone'))->diffForHumans(),
                    ];
                }));
        }

        return $paginator;
    }

    protected function doSearch($outerModel, $model, Request $request)
    {
        // Ensure that if items are selected, they're valid uuids
        $uuidCollection = collect(preg_split('/\s*[,|]\s*/', $request->get('selectedItems')))
            ->filter(function ($item) {
                return $item && Uuid::isValid($item);
            });

        // `$outerItemId` refers to the id of the item being edited (may be null).
        $outerItemId = $request->get('itemId');
        if (!Uuid::isValid($outerItemId)) {
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
        $isTypeSpecific = data_get($outerModel, sprintf('fields.%s.isTypeSpecific', $fieldInvokingRequest)) === true;
        return $this->dataService->findByType(
            $model,
            [
                'source' => 'admin-ajax',
                'outerModelType' => $outerModel->_type,
                'innerModelType' => $model->_type,
                'outerItemId' => $outerItemId,
                'resultsPerPage' => $resultsPerPage,
                'isFromAjaxController' => true,
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
                if ($model->_type == 'laramieUpload' && $lookupSubtype == 'image') {
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
                        foreach ($model->quickSearch as $searchFieldName) {
                            // for, we'll also search by id and tags
                            $searchField = data_get($model, 'fields.'.$searchFieldName);

                            // Is the search field is set to an html field? search by whatever the field is pointing to for sorting
                            if ($searchField->type == 'html') {
                                if (data_get($searchField, 'sortBy') && data_get($model, 'fields.'.data_get($searchField, 'sortBy'))) {
                                    $searchField = data_get($model, 'fields.'.$searchField->sortBy);
                                }
                            }

                            if ($searchField->type == 'computed') {
                                $query->orWhere(\DB::raw($searchField->sql), 'ilike', '%'.$keywords.'%');
                            } elseif ($searchField->type == 'markdown') {
                                $query->orWhere(\DB::raw('data#>>\'{'.$searchField->_fieldName.',markdown}\''), 'ilike', '%'.$keywords.'%');
                            } elseif (in_array($searchField->type, ['id', 'created_at', 'updated_at'])) {
                                $query->orWhere(\DB::raw($searchField->id), 'ilike', '%'.$keywords.'%');
                            } else {
                                $query->orWhere(\DB::raw('data->>\''.$searchField->_fieldName.'\''), 'ilike', '%'.$keywords.'%');
                            }
                        }

                        // Also search id just in case an item can't be found by its alias (multiple 'James Smiths' for example)
                        $query->orWhere(\DB::raw('id::text'), 'ilike', '%'.$keywords.'%');

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
                    $query->where(\DB::raw('data->>\'source\''), 'ilike', sprintf('%s.%s', $outerModel->_type, $fieldInvokingRequest));
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
        $comments = $this->dataService->getComments($id);

        return response()->json((object) [
            'tags' => $this->dataService->getTags($id),
            'comments' => $comments,
        ]);
    }

    /**
     * Delete a meta item. If it doesn't exist, return a json payload of `{ success: false }`. But it should be ok on
     * the frontend to silently fail if deletion doesn't work.
     *
     * @return \Illuminate\Http\Response
     */
    public function deleteMeta($modelKey, $id, Request $request)
    {
        // After deleting, return meta to repopulate holders
        $deletedItem = $this->dataService->deleteMeta($id);
        if ($deletedItem) {
            return $this->getMeta($modelKey, $deletedItem->laramie_data_id);
        }

        return response()->json((object) ['success' => false]);
    }

    /**
     * Add a tag to an item. Return all tags / comments after doing so.
     *
     * @return \Illuminate\Http\Response
     */
    public function addTag($modelKey, $id, Request $request)
    {
        // After saving, return a list of tags to repopulate tags holder.
        $this->dataService->createTag($id, $request->get('meta'));

        return $this->getMeta($modelKey, $id);
    }

    /**
     * Add a comment to an item. Return all tags / comments after doing so.
     *
     * @return \Illuminate\Http\Response
     */
    public function addComment($modelKey, $id, Request $request)
    {
        // After saving, return a list of comments to repopulate comments holder.
        $this->dataService->createComment($id, LaramieHelpers::getLaramieMarkdownObjectFromRawText($request->get('meta')));

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
        $alert = $this->dataService->findById('laramieAlert', $id);

        // If the item exists (as an alert), delete it:
        if (data_get($alert, 'id')) {
            $alert->status = 'Read';
            $this->dataService->save('laramieAlert', $alert);
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
        $userPrefs = $this->dataService->getUserPrefs();
        $userPrefs->hideTags = $request->get('hideTags') == '1';
        $userPrefs->hideRevisions = $request->get('hideRevisions') == '1';
        $this->dataService->saveUserPrefs($userPrefs);

        return response()->json((object) ['success' => true]);
    }

    public function modifyRef(Request $request, $modelKey)
    {
        $itemId = $request->get('itemId');
        if (!Uuid::isValid($itemId)) {
            $itemId = null;
        }

        $referenceItemId = $request->get('referenceId');
        if (!Uuid::isValid($referenceItemId)) {
            $referenceItemId = null;
        }

        if (!$itemId || !$referenceItemId) {
            abort(403);
        }

        $field = $request->get('field');

        $model = $this->dataService->getModelByKey($modelKey);
        $referenceField = data_get($model, sprintf( 'fields.%s', $field));
        $referenceFieldName = data_get($model, sprintf( 'fields.%s._fieldName', $field));
        $isSelected = $request->get('selected') === '1';

        $item = $this->dataService->findByIdSuperficial($model, $itemId);
        // TODO -- validate referenceItemId is NOT in the db (new item) or belongs to type: `data_get($referenceField, 'relatedModel')`

        $data = json_decode(data_get($item, '_origData', '{}'));

        // Single reference -- only one allowed at a time
        if (data_get($referenceField, 'subtype') == 'single') {
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

        DB::table('laramie_data')
            ->where('id', $item->id)
            ->update([
                'updated_at' => \Carbon\Carbon::now(config('laramie.timezone'))->toDateTimeString(),
                'data' => json_encode($data),
            ]);

        return response()->json((object) ['success' => true]);
    }
}
