<?php

namespace Laramie\Http\Controllers;

use DB;
use Illuminate\Http\Request;
use Exception;
use Validator;
use Ramsey\Uuid\Uuid;
use cogpowered\FineDiff\Granularity\Word;
use cogpowered\FineDiff\Diff;
use Laramie\Lib\LaramieHelpers;
use Laramie\Events\PreEdit;
use Laramie\Events\BulkDuplicate;
use Laramie\Events\BulkDelete;
use Laramie\Events\BulkExport;
use Laramie\Lib\LaramieModel;
use Laramie\Services\LaramieDataService;

/**
 * The AdminController is the primary application controller. Middleware is
 * assigned in `routes/web.php`.
 */
class AdminController extends Controller
{
    protected $dataService;
    private $validationRules = [];

    /**
     * Create a new AdminController.
     *
     * @param LaramieDataService $dataService Inject the service that talks to the db
     *
     * @return AdminController
     */
    public function __construct(LaramieDataService $dataService)
    {
        $this->dataService = $dataService;
    }

    /**
     * Return the dashboard.
     *
     * The dashboard and all content required to drive it is meant to be
     * manually implemented by each applicaiton leveraging Laramie. The dashboard
     * view is copied over to the main application by `php artisan
     * vendor:publish` during install, and can be found at
     * `resources/views/vendor/laramie/dashboard.blade.php`.
     *
     * Inject data into the view by modifying your application's
     * AppServiceProvider (or other). In your `AppServiceProvicer.php`, modify
     * the `boot` method to inject required data, for example:
     * ```
     * public function boot()
     * {
     *     ...
     *     // Inject data into the admin dashboard:
     *     \View::composer(['laramie::dashboard'], function ($view) {
     *         $view->with('data', app(LaramieDataService::class)->findByType('LaramieUser'));
     *     });
     * }
     *```
     *
     * @return \Illuminate\Http\Response
     */
    public function getDashboard()
    {
        $dashboardOverride = config('laramie.dashboard_override');
        if ($dashboardOverride && $dashboardOverride != 'vanilla') {
            return redirect()->to(route('laramie::list', ['modelKey' => $dashboardOverride]));
        } elseif ($dashboardOverride == 'vanilla') {
            return $this->getVanillaDashboard();
        }

        return view('laramie::dashboard');
    }

    private function getVanillaDashboard()
    {
        return view('laramie::vanilla-dashboard', [
            'menu' => $this->dataService->getMenu(),
            'user' => $this->dataService->getUser(),
            'dataService' => $this->dataService,
        ]);
    }

    /**
     * Return the list page for a model type.
     *
     * Applies sorting and filters to show just the data you're looking for.
     *
     * @return \Illuminate\Http\Response
     */
    public function getList($modelKey, Request $request, LaramieHelpers $viewHelper)
    {
        $options = collect($request->all())
            ->filter(function ($item) {
                return $item !== null && $item !== '';
            })
            ->all();

        // If there aren't any qs params, check to see if the referrer is
        // either this or its edit page. If not, check to see if there's a default
        // report to load. If so, load it.
        if (!count($options)) {
            $referrer = $request->headers->get('referer');
            $currentUrl = url()->current();
            if (strpos($referrer, $currentUrl) !== 0) {
                $defaultReport = $request->cookie('default_'.$modelKey);
                if ($defaultReport) {
                    $report = $this->dataService->findById('LaramieSavedReport', $defaultReport);
                    if (object_get($report, 'id')) {
                        return $this->reportRedirect($report);
                    }
                }
            }
        }

        $model = $this->dataService->getModelByKey($modelKey);

        // Check to see if this is a 'singular' model -- meaning there should only ever be one of them (like settings, etc).
        if (object_get($model, 'isSingular')) {
            return $this->redirectToSingularEdit($model);
        }

        // A user may have saved preferences for hiding / showing fields. Load those and ensure that if they exist they're a subset of the fields on the model.
        // The user's model prefs may include things like which columns to show, etc
        $userPrefs = $this->dataService->getUserPrefs();
        $userUuid = $this->dataService->getUserUuid();

        $filters = $this->getFilters($options);

        $options['filters'] = $filters;

        $models = $this->dataService->findByType($model, $options);

        $listableFields = $this->getListableFields($model, (object) object_get($userPrefs, $modelKey.'.listFields', []));

        $reports = $this->dataService->getUserReportsForModel($model);

        $listFields = $this->getListedFields($listableFields);

        $ids = collect($models->items())
            ->map(function ($item) {
                return $item->id;
            })
            ->all();

        if (array_get($listFields, '_versions')) {
            $this->setMeta('_versions', $models, $ids);
        }

        if (array_get($listFields, '_comments')) {
            $this->setMeta('_comments', $models, $ids);
        }

        if (array_get($listFields, '_tags')) {
            $this->setMeta('_tags', $models, $ids);
        }

        return view('laramie::list-page')
            ->with('model', $model)
            ->with('listableFields', $listableFields)
            ->with('listFields', $listFields)
            ->with('models', $models)
            ->with('filters', $filters)
            ->with('reports', $reports)
            ->with('viewHelper', $viewHelper);
    }

    private function setMeta($type, &$models, $ids)
    {
        $tmp = null;
        $map = [];

        switch ($type) {
            case '_versions':
                $tmp = $this->dataService->getNumVersions($ids);
                break;
            case '_tags':
                $tmp = $this->dataService->getNumTags($ids);
                break;
            case '_comments':
                $tmp = $this->dataService->getNumComments($ids);
                break;
        }

        foreach ($tmp as $item) {
            $map[$item->laramie_data_id] = $item;
        }

        foreach ($models as $model) {
            $item = array_get($map, $model->id, null);
            $model->{$type} = str_replace('{*count*}', object_get($item, 'count', 0), $model->{$type});
        }
    }

    /**
     * If a model is marked as `isSingular`, we should never see a list page.
     * Redirect to the `singleton` of that model.
     *
     * @return \Illuminate\Http\Response
     */
    private function redirectToSingularEdit($model)
    {
        $id = $this->dataService->getSingularItemId($model);

        return redirect()->to(route('laramie::edit', ['modelKey' => $model->_type, 'id' => $id]));
    }

    /**
     * Handle bulk actions -- these are triggered from the list page.
     *
     * Bulk actions (for now) must be one of the following:
     *
     * - duplicate // clone items
     * - delete    // delete items
     * - export    // export items to a csv
     *
     * Bulk actions trigger events (which must be synchronous) which in turn
     * actually field the logic of the bulk action.
     *
     * @return \Illuminate\Http\Response
     */
    public function bulkActionHandler($modelKey, Request $request)
    {
        $model = $this->dataService->getModelByKey($modelKey);
        $operation = $request->get('bulk-action-operation');

        $options = $request->all();
        $origSort = array_get($options, 'sort');
        $filters = $this->getFilters($options);
        $options['filters'] = $filters;
        $options['sort'] = 'id';

        switch ($operation) {
            case 'duplicate':
                event(new BulkDuplicate($model, $options));
                break;
            case 'delete':
                event(new BulkDelete($model, $options));
                break;
            case 'export':
                $options['sort'] = $origSort;
                $listableFields = $this->getListableFields($model);
                $outputFile = storage_path(Uuid::uuid4()->toString().'.csv');
                event(new BulkExport($model, $options, $listableFields, $outputFile));

                return response()->download($outputFile, sprintf('%s_%s.csv', snake_case($model->namePlural), date('Ymd')))->deleteFileAfterSend(true);
        }

        return $this->redirectToFilteredListPage($modelKey, $request);
    }

    /**
     * Save selection and ordering of fields on the page settings modal.
     *
     * @return \Illuminate\Http\Response
     */
    public function saveListPrefs($modelKey, Request $request)
    {
        $userPrefs = $this->dataService->getUserPrefs();
        $model = $this->dataService->getModelByKey($modelKey);
        $weight = 10;

        $listFields = [];
        foreach ($request->all() as $key => $value) {
            if (array_key_exists($key, $model->fields)) {
                $listFields[$key] = (object) ['weight' => $weight, 'listed' => $value == 1];
                $weight += 10;
            }
        }
        $userPrefs->{$model->_type} = object_get($userPrefs, $model->_type, (object) []);
        $userPrefs->{$model->_type}->listFields = $listFields;

        $this->dataService->saveUserPrefs($userPrefs);

        return $this->redirectToFilteredListPage($modelKey, $request);
    }

    /**
     * Redirect to a list page, preserving filters and sort as defined in the
     * `$request`.
     *
     * @return \Illuminate\Http\Response
     */
    private function redirectToFilteredListPage($modelKey, Request $request)
    {
        $filterString = collect($request->all())
            ->filter(function ($value, $key) {
                return preg_match('/^(filter|sort)/', $key);
            })
            ->map(function ($e, $key) {
                return sprintf('%s=%s', rawurlencode($key), rawurlencode($e));
            })
            ->values()
            ->implode('&');

        return redirect()->to(route('laramie::list', ['modelKey' => $modelKey]).sprintf('?%s', $filterString));
    }

    /**
     * Load a saved report (tantamount to loading filters and sort and
     * redirecting to list page with those applied.
     *
     * @return \Illuminate\Http\Response
     */
    public function loadReport($id)
    {
        $report = $this->dataService->findById($this->dataService->getModelByKey('LaramieSavedReport'), $id);

        return $this->reportRedirect($report);
    }

    private function reportRedirect($report)
    {
        if (object_get($report, 'relatedModel')) {
            return redirect()->to(route('laramie::list', ['modelKey' => $report->relatedModel]).sprintf('?%s', $report->filterString));
        }

        // something went wrong, redirect to dashboard
        return redirect()
            ->to(route('laramie::dashboard'))
            ->with('alert', (object) ['class' => 'is-danger', 'title' => 'Awww snap! That didn\'t work', 'alert' => 'Sorry, we couldn\'t find that saved report']);
    }

    /**
     * Load a saved report (tantamount to loading filters and sort and
     * redirecting to list page with those applied.
     *
     * @return \Illuminate\Http\Response
     */
    public function modifyReport($id, Request $request)
    {
        $report = $this->dataService->findById('LaramieSavedReport', $id);
        $relatedModel = object_get($report, 'relatedModel');

        if ($relatedModel) {
            $modificationType = $request->get('type');

            switch ($modificationType) {
                case 'set-default':
                    return response('OK')->cookie('default_'.$relatedModel, $id, (10 * 365 * 24 * 60));
                    break;
                case 'delete':
                    $this->dataService->deleteById($report->id, true);
                    break;
                break;
            }
        }

        return null;
    }

    /**
     * Save a "report" (a collection of filters and applied sort) for
     * convenience.
     *
     * @return \Illuminate\Http\Response
     */
    public function saveReport($modelKey, Request $request)
    {
        $model = $this->dataService->getModelByKey($modelKey);
        $reportName = $request->get('report-name');

        $userUuid = $this->dataService->getUserUuid();

        $reportModel = $this->dataService->getModelByKey('LaramieSavedReport');

        // Delete reports with the same name by the same user for the same model
        $tmp = collect($this->dataService->findByType($reportModel, ['results-per-page' => 0], function ($query) use ($model, $reportName, $userUuid) {
            $query->where(\DB::raw('data->>\'user\''), $userUuid)
                ->where(\DB::raw('data->>\'relatedModel\''), $model->_type)
                ->where(\DB::raw('data->>\'name\''), $reportName);
        }))
            ->each(function ($e) {
                $this->dataService->deleteById($e->id, true);
            });

        $filterString = collect($request->all())
            ->except(['page', 'report-name', '_token'])
            ->map(function ($e, $key) {
                return sprintf('%s=%s', rawurlencode($key), rawurlencode($e));
            })
            ->values()
            ->implode('&');

        $report = new LaramieModel();
        $report->user = $this->dataService->getUser();
        $report->relatedModel = $model->_type;
        $report->name = $reportName;
        $report->key = str_random(10);
        $report->filterString = $filterString;

        $report = $this->dataService->save($reportModel, $report);

        return $this->redirectToFilteredListPage($modelKey, $request);
    }

    /**
     * Return the edit screen for a model item.
     *
     * @return \Illuminate\Http\Response
     */
    public function getEdit($modelKey, $id, Request $request)
    {
        $model = $this->dataService->getModelByKey($modelKey);

        if (!$model->isEditable) {
            throw new Exception('Items of this type may not be edited');
        }

        // If there's an error on the post, `item` will have been flashed to the session
        $item = session('item') ?: $this->dataService->findById($model, $id);
        $lastEditor = null;
        $lastEditorId = object_get($item, 'user_id');
        if (Uuid::isValid($lastEditorId)) {
            $lastEditor = $this->dataService->findByIdSuperficial('LaramieUser', $lastEditorId);
        }
        $metaId = session('metaId') ?: ($item->_isUpdate ? $item->id : Uuid::uuid1()->toString());
        $selectedTab = session('selectedTab') ?: '_main';
        $errorMessages = session('errorMessages') ?: null;
        $revisions = $this->dataService->findItemRevisions($id);

        // Ensure that the user can't create a new 'singular' item
        if ($item->_isNew && object_get($model, 'isSingular')) {
            return $this->redirectToSingularEdit($model);
        }

        $lastUserToUpdate = null;
        if ($item->_isUpdate) {
            $lastUserToUpdate = $this->dataService->findByIdSuperficial($this->dataService->getModelByKey('LaramieUser'), $item->user_id);
        }

        /*
         * Fire pre-edit event: listeners MUST be synchronous. This event enables
         * the ability to dynamically alter the model that will be edited based on
         * the injected arguments.
         */
        event(new PreEdit($model, $item, $this->dataService->getUser()));

        return view('laramie::edit-page')
            ->with('model', $model)
            ->with('modelKey', $modelKey)
            ->with('item', $item)
            ->with('metaId', $metaId)
            ->with('selectedTab', $selectedTab)
            ->with('errorMessages', $errorMessages)
            ->with('lastUserToUpdate', $lastUserToUpdate)
            ->with('user', $this->dataService->getUser())
            ->with('lastEditor', $lastEditor)
            ->with('revisions', $revisions);
    }

    /**
     * Handle edit form posts. As we're allowing deeply nested data, it
     * necessarily gets a little gnarly.
     *
     * @return \Illuminate\Http\Response
     */
    public function postEdit($modelKey, $id, Request $request)
    {
        $model = $this->dataService->getModelByKey($modelKey);
        $item = $this->dataService->findById($model, $id);
        $metaId = $request->get('_metaId');
        $selectedTab = $request->get('_selectedTab');

        $isNew = $item->_isNew;

        event(new PreEdit($model, $item, $this->dataService->getUser()));

        // Load item with new values _before_ validation. If there are errors, flash updated item and redirect.
        foreach ($model->fields as $fieldName => $field) {
            $item->{$fieldName} = $this->updateField($field);
        }

        $success = true;
        $errors = null;
        $errorMessages = null;

        $validationMessageOverrides = collect(\Lang::get('validation'))
            ->map(function ($item) {
                return str_ireplace('the :attribute', 'This', $item);
            })
            ->toArray();

        // First step of validation: validate using Laravel's validator
        if (array_filter($this->validationRules)) {
            $validator = Validator::make($request->all(), $this->validationRules, $validationMessageOverrides);
            $success = $validator->passes();
            if (!$success) {
                $errors = $validator;
                $errorMessages = $errors->errors()->getMessages();
            }
        }

        // If we pass the first step of validation, save the model. NOTE:
        // within the save method there is an additional stage of validation that
        // utilizes the model's json schema.
        if ($success) {
            \DB::beginTransaction();
            try {
                $item = $this->dataService->save($model, $item);
                \DB::commit();
            } catch (\Exception $e) {
                \DB::rollBack();
                $success = false;
                $errors = ['schemaError' => true, 'message' => $e->getMessage()];
            }
        }

        if (!$success) {
            $alert = (object) ['class' => 'is-danger', 'title' => 'Awww snap! That didn\'t work', 'alert' => sprintf('There was an error saving the %s. Please review the form, address all errors, and try again.', $model->name)];
            if (is_array($errors) && array_key_exists('schemaError', $errors)) {
                $alert->alert = $alert->alert.'<br>'.$errors['message'];
            }

            return redirect()
                ->route('laramie::edit', ['modelKey' => $modelKey, $id => $id])
                ->with('item', $item)
                ->with('alert', $alert)
                ->with('metaId', $metaId)
                ->with('selectedTab', $selectedTab)
                ->with('errorMessages', $errorMessages)
                ->withErrors($errors);
        }

        if ($isNew) {
            // Update meta that may have been created to point to this new item:
            $this->dataService->updateMetaIds($metaId, $item->id);
        }

        return redirect()
            ->route('laramie::edit', ['modelKey' => $modelKey, $id => $item->id])
            ->with('selectedTab', $selectedTab)
            ->with('alert', (object) ['title' => 'Success!', 'alert' => sprintf('The %s was successfully %s.', $model->name, $id == 'new' ? 'created' : 'updated')])
            ->with('status', 'saved');
    }

    /**
     * Depth-first update a model field. Recursively dives back into itself for
     * aggregate fields.
     *
     * @return mixed $value
     */
    private function updateField($field, $prefix = null)
    {
        $request = request();
        $fieldName = $prefix.$field->_fieldName;

        $fieldValidation = object_get($field, 'validation');
        if ($fieldValidation) {
            $this->validationRules[$fieldName] = $fieldValidation;
        }

        $value = $request->get($fieldName);

        switch ($field->type) {
            case 'currency':
            case 'integer':
            case 'number':
            case 'range':
                return $value === null ? null : (float) $request->get($fieldName);

            case 'boolean':
                if ($value === '1') {
                    return true;
                } elseif ($value === '0') {
                    return false;
                }

                return null;

            case 'markdown':
                return (object) ['markdown' => $value, 'html' => LaramieHelpers::markdownToHtml($value)];

            case 'password':
                if ($request->get('_'.$fieldName)) {
                    // The 'keep' checkbox was checked
                    return LaramieHelpers::getLaramiePasswordObjectFromPasswordText($request->get('_'.$fieldName));
                }

                return LaramieHelpers::getLaramiePasswordObjectFromPasswordText($value);

            case 'timestamp':
                $date = $request->get($fieldName.'-date');
                $time = $request->get($fieldName.'-time');
                $timezone = $request->get($fieldName.'-timezone');
                if ($date && $time && $timezone) {
                    $c = new \Carbon\Carbon(sprintf('%s %s', $date, $time), $timezone);

                    return LaramieHelpers::getLaramieTimestampObjectFromCarbonDate($c);
                }

                return null;

            case 'file':
            case 'image':
                if ($request->hasFile($fieldName)) {
                    // First, check to see if we need to remove an old file
                    //$this->dataService->removeFile(object_get($item, $fieldName));
                    // If it's a new upload, store a record of it -- when the item is edited in the future, we'll use the record to persist upload details.

                    // Validate the file _before_ we save it; this fixes the
                    // issue where validation can essentially be bypassed by
                    // using the keep functionality post validation fail.
                    if ($fieldValidation) {
                        $isValid = Validator::make([$fieldName => $request->file($fieldName)], [$fieldName => $fieldValidation])->passes();
                        if (!$isValid) {
                            return null;
                        }
                    }

                    return $this->dataService->saveFile(
                        $request->file($fieldName),
                        object_get($field, 'isPublic', config('laramie.files_are_public_by_default', false))
                    );
                } elseif ($request->get('_'.$fieldName)) {
                    // The 'keep' checkbox was checked.
                    return $this->dataService->getFileInfo($request->get('_'.$fieldName));
                } else {
                    // check to see if we need to remove an old file
                    //$this->dataService->removeFile(object_get($item, $fieldName));
                    return null;
                }
                break;

            case 'reference':
                $tmp = null;
                $uuid = $value;
                $tmpModel = $this->dataService->getModelByKey($field->relatedModel);
                if ($field->subtype == 'single' && $uuid && Uuid::isValid($uuid)) {
                    // Single refs, non-array value of uuid
                    $tmp = $this->dataService->findById($tmpModel, $uuid);
                } else {
                    // Multi refs, array value of uuids
                    $tmp = collect(preg_split('/\s*[,|]\s*/', $uuid))
                        ->filter(function ($item) {
                            return $item && Uuid::isValid($item);
                        })
                        ->map(function ($e) use ($tmpModel) {
                            return $this->dataService->findById($tmpModel, $e);
                        })
                        ->values()
                        ->all();
                }

                return $tmp;

            case 'aggregate':
                // Find the unique keys for this aggregate:
                $itemPrefixes = collect(array_keys($request->all()))
                    ->map(function ($e) use ($fieldName) {
                        preg_match(sprintf('/(?<prefix>(^.*_?)?%s_[^_]+_)/', $fieldName), $e, $matches);
                        $prefix = array_get($matches, 'prefix', null);
                        // For file fields, the prefix that's picked up above may begin with an underscore -- if the `keep` checkbox is checked. But we need the field version.
                        if (strpos($prefix, '_') === 0) {
                            $prefix = substr($prefix, 1);
                        }

                        return $prefix;
                    })
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();

                $aggregateItem = $field->isRepeatable ? [] : null;

                $origPrefix = $prefix;

                // Recursively dive back in.
                foreach ($itemPrefixes as $prefix) {
                    $tmp = preg_replace('/[_]+$/', '', $prefix);
                    //var_dump(substr($tmp, strrpos(preg_replace('/[_]+$/', '', $prefix), '_'); die();
                    $oldKey = substr($tmp, strrpos($tmp, '_') + 1);
                    $item = (object) ['_key' => $oldKey];
                    foreach ($field->fields as $subfieldName => $subfield) {
                        $item->{$subfieldName} = $this->updateField($subfield, $prefix);
                    }

                    if ($field->isRepeatable) {
                        $aggregateItem[] = $item;
                    } else {
                        $aggregateItem = $item;
                    }
                }

                return $aggregateItem;

            default:
                return $value;
        }
    }

    public function alertRedirect($id)
    {
        $meta = $this->dataService->getEditInfoForMetaItem($id);
        if ($meta) {
            return redirect()->to(route('laramie::edit', (array) $meta).'?highlight-comment='.$id);
        }

        return redirect()->route('laramie::dashboard')
            ->with('alert', (object) ['class' => 'is-warning', 'title' => 'Notification not found', 'alert' => 'Well, this is a little embarrassing. The original notification has been removed, so we can\'t send you to its original context.']);
    }

    /**
     * Endpoint to delete an item (must be accessed by 'DELETE' method.
     *
     * @return \Illuminate\Http\Response
     */
    public function deleteItem($modelKey, $id, Request $request)
    {
        $this->dataService->deleteById($id);

        if ($request->ajax()) {
            return response()->json(['success' => true]);
        }

        return redirect()->to(route('laramie::list', ['modelKey' => $modelKey]));
    }

    /**
     * Endpoint to delete a historical revision.
     *
     * @return \Illuminate\Http\Response
     */
    public function deleteRevision($modelKey, $revisionId)
    {
        $this->dataService->deleteRevision($revisionId);

        return response()->json(['success' => true]);
    }

    /**
     * Endpoint to restore a historical revision.
     *
     * @return \Illuminate\Http\Response
     */
    public function restoreRevision($modelKey, $revisionId)
    {
        $item = $this->dataService->restoreRevision($revisionId);
        $model = $this->dataService->getModelByKey($item->type);
        $alert = (object) ['class' => 'is-warning', 'title' => 'Revision loaded', 'alert' => sprintf('The revision from %s has been loaded successfully.', \Carbon\Carbon::parse($item->updated_at)->toDayDateTimeString())];


        return redirect()->route('laramie::edit', ['modelKey' => $model->_type, 'id' => $item->laramie_data_id])
            ->with('alert', $alert);
    }

    /**
     * Endpoint to compare two revisions. Note that not all fields can be
     * compared currently (aggregates).
     *
     * @return \Illuminate\Http\Response
     */
    public function compareRevisions($modelKey, $revisionId)
    {
        $differ = new Diff(new Word());

        $item = $this->dataService->getItemRevision($revisionId);
        $model = $this->dataService->getModelByKey($modelKey);

        $itemId = object_get($item, 'laramie_data_id');

        // If an item wasn't found found in the archive, it's likely because the current item was selected
        if (!$item->id) {
            $item = $this->dataService->findById($model, $revisionId);
            $itemId = $revisionId;
            $revisionId = null;
        }

        if (!$item->id) {
            throw new Exception('Could not find item');
        }

        $previousItem = $this->dataService->findPreviousItem($itemId, $revisionId);

        $diffs = [];

        foreach (object_get($model, 'fields') as $key => $field) {
            $diff = null;

            switch ($field->type) {
                case 'computed':
                case 'password':
                    break;
                case 'aggregate':
                    // Aggregates pose a bit of a challenge -- we could recurse into them... but doing a diff on their json encoded data is easier. I'm fine with that for now.
                    $diff = $differ->render(json_encode(object_get($previousItem, $key, '{}'), JSON_PRETTY_PRINT), json_encode(object_get($item, $key, '{}'), JSON_PRETTY_PRINT));
                    break;
                case 'file':
                case 'image':
                case 'reference':
                    $a = object_get($item, $key, '');
                    $a = collect(is_array($a) ? $a : [$a])
                        ->map(function ($e) {
                            // Depending on context, we may be getting an object (diffing from **current** `$item`), or a string (diffing two old items).
                            return gettype($e) == 'string' ? $e : object_get($e, 'id');
                        })
                        ->filter()
                        ->all();
                    $b = object_get($previousItem, $key, '');
                    $b = array_filter(is_array($b) ? $b : [$b]);

                    $aAliases = [];
                    $bAliases = [];
                    $tmpRelatedModel = $this->dataService->getModelByKey($field->relatedModel);

                    foreach ($a as $id) {
                        $tmp = $this->dataService->findById($field->relatedModel, $id);
                        $aAliases[] = object_get($tmp, object_get($tmpRelatedModel, 'alias', 'id'));
                    }

                    foreach ($b as $id) {
                        $tmp = $this->dataService->findById($field->relatedModel, $id);
                        $bAliases[] = object_get($tmp, object_get($tmpRelatedModel, 'alias', 'id'));
                    }

                    $diff = $differ->render(implode(', ', $bAliases), implode('', $aAliases));
                    break;
                case 'markdown':
                    $diff = $differ->render(object_get($previousItem, "$key.markdown", ''), object_get($item, "$key.markdown", ''));
                    break;
                case 'timestamp':
                    $previous = sprintf('%s %s %s', object_get($previousItem, "$key.date", ''), object_get($previousItem, "$key.time", ''), object_get($previousItem, "$key.timezone", ''));
                    $current = sprintf('%s %s %s', object_get($item, "$key.date", ''), object_get($item, "$key.time", ''), object_get($item, "$key.timezone", ''));
                    $diff = $differ->render($previous, $current);
                    break;
                default:
                    $diff = $differ->render(object_get($previousItem, $key, ''), object_get($item, $key, ''));
                    break;
            }

            if ($diff !== null) {
                $diffs[] = (object) [
                    'label' => $field->label,
                    'left' => preg_replace('/<ins>.*?<\/ins>/', '', $diff),
                    'right' => preg_replace('/<del>.*?<\/del>/', '', $diff),
                ];
            }
        }

        $hasPrevious = (bool) $previousItem->id;
        $leftLabel = $hasPrevious ? sprintf('Item from %s', \Carbon\Carbon::parse($previousItem->updated_at)->toDayDateTimeString()) : '--';
        $rightLabel = sprintf('Item from %s', \Carbon\Carbon::parse($item->updated_at)->toDayDateTimeString());

        return view(request()->ajax() ? 'laramie::partials.revision-comparison-table' : 'laramie::revision-compare')
            ->with('model', $model)
            ->with('leftLabel', $leftLabel)
            ->with('rightLabel', $rightLabel)
            ->with('diffs', $diffs);
    }
}
