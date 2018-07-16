<?php

namespace Laramie\Http\Controllers;

use Illuminate\Http\Request;
use Ramsey\Uuid\Uuid;
use Laramie\Lib\LaramieHelpers;
use Laramie\Services\LaramieDataService;

/**
 * The AjaxController is primarily responsible for handling all the AJAX interactions initiated by the list and edit
 * pages.
 */
class AjaxController extends Controller
{
    protected $dataService;

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
    public function getList($modelKey, $listModelKey, Request $request)
    {
        $model = $this->dataService->getModelByKey($listModelKey);

        // Ensure that if items are selected, they're valid uuids
        $uuidCollection = collect(preg_split('/\s*[,|]\s*/', $request->get('selectedItems')))
            ->filter(function ($item) {
                return $item && Uuid::isValid($item);
            });

        // `$itemId` refers to the id of the item being edited (may be null).
        $itemId = $request->get('itemId');

        // `$keywords` is the search string.
        $keywords = $request->get('keywords');

        // `$lookupSubtype` refers to what kind of reference field is being searched (image, file, etc).
        $lookupSubtype = $request->get('lookupSubtype');

        $paginator = $this->dataService->findByType(
            $model,
            ['results-per-page' => 10],
            function ($query) use ($uuidCollection, $keywords, $model, $lookupSubtype, $itemId) {
                // Never show the item being edited. Is there ever a need for an item to be its own parent? None that I
                // can think of
                $query->where('id', '!=', $itemId);

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
                // If searching LaramieUploads, limit returned extensions if the subtype is `image`.
                if ($model->_type == 'LaramieUpload' && $lookupSubtype == 'image') {
                    $query->whereRaw(\DB::raw('data->>\'extension\' in (\''.implode("','", config('laramie.allowed_image_types')).'\')'));
                }
                // Limit by keyword
                $query->where(function ($query) use ($uuidCollection, $keywords, $model) {
                    if ($keywords) {
                        if (count($uuidCollection)) {
                            $query->orWhereIn('id', $uuidCollection->all());
                        }
                        // Primarily use the model's alias to search by. If they still can't find what they're looking

                        // for, we'll also search by id and tags
                        $alias = object_get($model, 'fields.'.$model->alias);
                        if ($alias->type == 'computed') {
                            $query->orWhere(\DB::raw($alias->sql), 'ilike', '%'.$keywords.'%');
                        } elseif (in_array($alias->type, ['id', 'created_at', 'updated_at'])) {
                            $query->orWhere(\DB::raw($alias->id), 'ilike', '%'.$keywords.'%');
                        } else {
                            $query->orWhere(\DB::raw('data->>\''.$alias->_fieldName.'\''), 'ilike', '%'.$keywords.'%');
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
            }
        );

        // The name only needs to be unique per reference in case it's a radio select (this value isn't being submitted).
        $name = str_random(10);

        $paginator->setCollection($paginator->getCollection()
            ->map(function ($e) use ($model, $name) {
                return (object) ['id' => $e->id, 'name' => $name, 'label' => object_get($e, $model->alias), 'selected' => object_get($e, 'selected') == 1];
            }));

        return $paginator;
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
        $this->dataService->createComment($id, (object) [
            'html' => LaramieHelpers::markdownToHtml($request->get('meta')),
            'markdown' => $request->get('meta'),
        ]);

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
        $alert = $this->dataService->findById('LaramieAlert', $id);

        // If the item exists (as an alert), delete it:
        if (object_get($alert, 'id')) {
            $alert->status = 'Read';
            $this->dataService->save('LaramieAlert', $alert);
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
}
