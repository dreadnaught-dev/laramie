<?php

namespace Laramie\Http\Controllers;

use DB;
use Illuminate\Http\Request;
use Exception;
use Validator;
use Str;
use cogpowered\FineDiff\Granularity\Word;
use cogpowered\FineDiff\Diff;

use Laramie\Hook;
use Laramie\Lib\LaramieHelpers;
use Laramie\Hooks\HandleBulkAction;
use Laramie\Hooks\PreList;
use Laramie\Hooks\PostList;
use Laramie\Hooks\PreEdit;
use Laramie\Hooks\TransformModelForEdit;
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
    private $modelKey = null;

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
     *         $view->with('data', app(LaramieDataService::class)->findByType('laramieUser'));
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

        $options['isFromAdmin'] = true;

        session()->put('_laramie_last_list_url', $request->fullUrl());

        // If there aren't any qs params, check to see if the referrer is
        // either this or its edit page. If not, check to see if there's a default
        // report to load. If so, load it.
        if (!count($options)) {
            $referrer = $request->headers->get('referer');
            $currentUrl = url()->current();
            if (strpos($referrer, $currentUrl) !== 0) {
                $defaultReport = $request->cookie('default_'.$modelKey);
                if ($defaultReport) {
                    $report = $this->dataService->findById('laramieSavedReport', $defaultReport);
                    if (data_get($report, 'id')) {
                        return $this->reportRedirect($report);
                    }
                }
            }
        }

        $model = $this->dataService->getModelByKey($modelKey);

        if (!$model->isListable) {
            throw new Exception('Items of this type may not be listed');
        }

        // Check to see if this is a 'singular' model -- meaning there should only ever be one of them (like settings, etc).
        if (data_get($model, 'isSingular')) {
            return $this->redirectToSingularEdit($model);
        }

        $extra = (object) $request->all();

        $extra->alert = session()->get('alert');

        // Fire the `PreList` event. This allows for a user to specify a redirect response if necessary
        Hook::fire(new PreList($model, $this->dataService->getUser(), $extra));

        $modelPrefsKey = data_get($extra, 'modelPrefsKey', $modelKey);

        if (data_get($extra, 'response')) {
            return data_get($extra, 'response');
        }

        $filters = LaramieHelpers::extractFiltersFromData($options);

        $options['filters'] = $filters;
        $options['quickSearch'] = $request->get('quick-search');
        $options['sortDirection'] = $request->get('sort-direction');
        $options['source'] = 'admin';

        // A user may have saved preferences for hiding / showing fields. Load those and ensure that if they exist they're a subset of the fields on the model.
        // The user's model prefs may include things like which columns to show, etc
        $userPrefs = $this->dataService->getUserPrefs();

        $reports = $this->dataService->getUserReportsForModel($model);

        $models = $this->dataService->findByType($model, $options);

        $listableFields = $this->getListableFields($model, (object) data_get($userPrefs, $modelPrefsKey.'.listFields', []));

        $listFields = $this->getListedFields($listableFields);

        $extra = (object) ['listFields' => data_get($options, 'listFields', $listFields), 'filters' => $filters, 'alert' => data_get($extra, 'alert')]; // passing this so we have context in the post list event;

        $listView = data_get($model, 'listView', 'laramie::list-page');

        $extra->response = view($listView)
            ->with('model', $model)
            ->with('listableFields', $listableFields)
            ->with('listFields', $listFields)
            ->with('models', $models)
            ->with('filters', $filters)
            ->with('reports', $reports)
            ->with('viewHelper', $viewHelper)
            ->with('alert', data_get($extra, 'alert'));

        // Fire the `PostList` event -- This allows for augmenting the items about to be shown on the list page (strictly for the list page). There's a PostFetch event that one should use if one needs to augment data fetched from the dataService _everywhere_.
        Hook::fire(new PostList($model, $models, $this->dataService->getUser(), $extra));

        return data_get($extra, 'response');
    }

    public function goBack($modelKey)
    {
        $redirectUrl = session()->get('_laramie_last_list_url', route('laramie::dashboard'));

        // If one jumped to another edit page from a relationship field or something, don't redirect back to a potentially different model's list page:
        if (strpos($redirectUrl, $modelKey) === false) {
            $redirectUrl = route('laramie::list', ['modelKey' => $modelKey]);
        }

        return redirect()->to($redirectUrl);
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
     * Handle bulk actions (triggered from the list page).
     *
     * @return \Illuminate\Http\Response
     */
    public function bulkActionHandler($modelKey, Request $request)
    {
        $model = $this->dataService->getModelByKey($modelKey);
        $nameOfBulkAction = $request->get('bulk-action-operation');

        $postData = $request->all();
        $filters = LaramieHelpers::extractFiltersFromData($postData);
        $postData['filters'] = $filters;
        $postData['quickSearch'] = $request->get('quick-search');
        $postData['sort'] = data_get($postData, 'sort', 'id');

        $user = $this->dataService->getUser();
        $query = $this->dataService->getBulkActionQuery($modelKey, $postData);

        $extra = (object) [
            'response' => $this->redirectToFilteredListPage($modelKey, $request),
            'listableFields' => $this->getListableFields($model), // inject context of what fields the list page is showing
        ];

        $alert = null;

        DB::beginTransaction();
        try {
            // Execute the bulk action
            Hook::fire(new HandleBulkAction($model, $nameOfBulkAction, $query, $postData, $user, $extra));

            DB::commit();
        } catch (\Illuminate\Database\QueryException $e) {
            DB::rollBack();
            $extra->response = $this->redirectToFilteredListPage($modelKey, $request);
            $alert = (object) ['class' => 'is-danger', 'title' => 'Awww snap! That didn\'t work', 'alert' => config('app.debug') ? $e->getMessage() : 'Sorry, there was an error performing that action.'];
        } catch (Exception $e) {
            DB::rollBack();
            $extra->response = $this->redirectToFilteredListPage($modelKey, $request);
            $alert = (object) ['class' => 'is-danger', 'title' => 'Awww snap! That didn\'t work', 'alert' => $e->getMessage()];
        }

        $response = data_get($extra, 'response');

        return $alert
            ? $response->with('alert', $alert)
            : $response;
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
        $modelPrefsKey = $request->get('model-prefs-key', $model->_type);
        $weight = 10;

        $listFields = [];

        $fieldsFromRequest = collect($request->all())
            ->filter(function($item, $key) {
                return preg_match('/^_lf_/', $key);
            });

        foreach ($fieldsFromRequest as $key => $value) {
            $key = preg_replace('/^_lf_/', '', $key);
            if (array_key_exists($key, $model->fields)) {
                $listFields[$key] = (object) ['weight' => $weight, 'listed' => $value == 1];
                $weight += 10;
            }
        }

        $userPrefs->{$modelPrefsKey} = data_get($userPrefs, $modelPrefsKey, (object) []);
        $userPrefs->{$modelPrefsKey}->listFields = $listFields;

        $this->dataService->saveUserPrefs($userPrefs);

        return redirect()->to(url()->previous());
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
                return !preg_match('/^([_]|bulk)/', $key);
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
        $report = $this->dataService->findById($this->dataService->getModelByKey('laramieSavedReport'), $id);

        return $this->reportRedirect($report);
    }

    private function reportRedirect($report)
    {
        if (data_get($report, 'relatedModel')) {
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
        $report = $this->dataService->findById('laramieSavedReport', $id);
        $relatedModel = data_get($report, 'relatedModel');

        if ($relatedModel) {
            $modificationType = $request->get('type');

            switch ($modificationType) {
                case 'set-default':
                    return response('OK')->cookie('default_'.$relatedModel, $id, (10 * 365 * 24 * 60));
                    break;
                case 'delete':
                    $this->dataService->deleteById('laramieSavedReport', $report->id, true);
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

        $reportModel = $this->dataService->getModelByKey('laramieSavedReport');

        // Delete reports with the same name by the same user for the same model
        // @TODO -- move this out of the controller
        $tmp = collect($this->dataService->findByType($reportModel, ['resultsPerPage' => 0], function ($query) use ($model, $reportName, $userUuid) {
            $query->where(DB::raw('data->>\'user\''), $userUuid)
                ->where(DB::raw('data->>\'relatedModel\''), $model->_type)
                ->where(DB::raw('data->>\'name\''), $reportName);
        }))
        ->each(function ($e) {
            $this->dataService->deleteById($modelKey, $e->id, true);
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
        $report->key = Str::random(10);
        $report->filterString = $filterString;

        $report = $this->dataService->save($reportModel, $report);

        return $this->redirectToFilteredListPage($modelKey, $request);
    }

    /**
     * Return the edit screen for a model item.
     *
     * @return \Illuminate\Http\Response
     */
    public function getEdit(Request $request, $modelKey, $id, array $extraInfoToPassToEvents = [])
    {
        $model = $this->dataService->getModelByKey($modelKey);

        if (!$model->isEditable) {
            throw new Exception('Items of this type may not be edited');
        }

        $this->dataService->removeFromCache($id);

        // If there's an error on the post, `item` will have been flashed to the session
        $item = session('item') ?: $this->dataService->findById($model, $id, 1);

        if (Str::isUuid($id) && $item === null) {
            abort(404);
        }

        // If we're editing a new item, check to see if we need to pre-set any of the singular relationships (from QS)
        if ($item->_isNew && !session('isFromPost')) {
            $singularRefs = collect($model->fields)
                ->filter(function($item, $key) use($request) {
                    return $item->type == 'reference'
                    && $item->subtype == 'single'
                    && $request->get($key);
                });
            foreach ($singularRefs as $key => $field) {
                $item->{$key} = $this->dataService->findById($field->relatedModel, $request->get($key));
            }
        }

        $lastEditor = null;
        $lastEditorId = data_get($item, 'user_id');
        if (Str::isUuid($lastEditorId)) {
            $lastEditor = $this->dataService->findByIdSuperficial('laramieUser', $lastEditorId);
        }
        $metaId = session('metaId') ?: ($item->_isUpdate ? $item->id : Str::orderedUuid());
        $selectedTab = session('selectedTab') ?: '_main';
        $errorMessages = session('errorMessages') ?: null;

        $revisions = [];
        if (!(config('laramie.disable_revisions') || data_get($model, 'disableRevisions'))) {
            $revisions = $this->dataService->findItemRevisions($id);
        }

        // Ensure that the user can't create a new 'singular' item
        if ($item->_isNew && data_get($model, 'isSingular')) {
            return $this->redirectToSingularEdit($model);
        } elseif (data_get($model, 'isSingular')) {
            session()->put('_laramie_last_list_url', route('laramie::dashboard'));
        }

        $lastUserToUpdate = null;
        if ($item->_isUpdate) {
            $lastUserToUpdate = $this->dataService->findByIdSuperficial($this->dataService->getModelByKey('laramieUser'), $item->user_id);
        }

        /*
         * Fire pre-edit event: listeners MUST be synchronous. This event enables
         * the ability to dynamically alter the model that will be edited based on
         * the injected arguments.
         */
        $user = $this->dataService->getUser();

        $sidebars = ['laramie::partials.edit.save-box' => ['item' => $item, 'user' => $user, 'lastUserToUpdate' => $lastUserToUpdate]];

        if (!(config('laramie.disable_meta') || data_get($model, 'disableMeta'))) {
            $sidebars['laramie::partials.edit.meta-box'] = ['user' => $user];
        }

        if (count($revisions) > 0) {
            $sidebars['laramie::partials.edit.revisions-box'] = ['item' => $item, 'revisions' => $revisions, 'model' => $model, 'user' => $user, 'lastEditor' => $lastEditor];
        }

        $extraInfoToPassToEvents = (object) $extraInfoToPassToEvents;

        $extraInfoToPassToEvents->sidebars = $sidebars;
        $extraInfoToPassToEvents->alert = session()->get('alert');
        $extraInfoToPassToEvents->formStatus = session()->get('formStatus');

        // Generally speaking, if you need to dynamically alter your model for edit, do so in this event:
        Hook::fire(new TransformModelForEdit($model,  $item, $user));

        // If you need to modify your _item_ for edit, generally do so here:
        Hook::fire(new PreEdit($model, $item, $user, $extraInfoToPassToEvents));

        if (data_get($extraInfoToPassToEvents, 'response')) {
            return $extraInfoToPassToEvents->response;
        }

        $editView = data_get($model, 'editView', 'laramie::edit-page');

        return view($editView)
            ->with('model', $model)
            ->with('modelKey', $modelKey)
            ->with('item', $item)
            ->with('metaId', $metaId)
            ->with('selectedTab', $selectedTab)
            ->with('errorMessages', $errorMessages)
            ->with('user', $user)
            ->with('sidebars', data_get($extraInfoToPassToEvents, 'sidebars', []))
            ->with('alert', data_get($extraInfoToPassToEvents, 'alert'));
    }

    /**
     * Handle edit form posts. As we're allowing deeply nested data, it
     * necessarily gets a little gnarly.
     *
     * @return \Illuminate\Http\Response
     */
    public function postEdit(Request $request, $modelKey, $id)
    {
        $this->modelKey = $modelKey;
        $model = $this->dataService->getModelByKey($modelKey);
        $this->dataService->removeFromCache($id);
        $item = $this->dataService->findById($model, $id);
        $metaId = $request->get('_metaId');
        $selectedTab = $request->get('_selectedTab');

        $isNew = $item->_isNew;

        // Fire `TransformModelForEdit` to give opportunity to hooks to change model (to dynamically change field types, etc).
        Hook::fire(new TransformModelForEdit($model, $item, $this->dataService->getUser()));

        // Load item with new values _before_ validation. If there are errors, flash updated item and redirect.
        foreach ($model->fields as $fieldName => $field) {
            $item->{$fieldName} = $this->updateField($field);
        }

        $success = true;
        $errors = null;
        $errorMessages = null;

        $validationMessageOverrides = collect(\Lang::get('validation'))
            ->map(function ($item) {
                return is_string($item)
                    ? str_ireplace('the :attribute', 'This', $item)
                    : $item;
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
            try {
                $item->_metaId = $metaId;
                $item = $this->dataService->save($model, $item);
            } catch (Exception $e) {
                $success = false;
                $errors = ['schemaError' => true, 'message' => $e->getMessage()];
            }
        }

        $redirectRouteParams = ['modelKey' => $modelKey, 'id' => $id];

        if ($request->get('is-child')) {
            $redirectRouteParams['is-child'] = 1;
        }

        $previousUrl = url()->previous();

        if (!$success) {
            $alert = (object) ['class' => 'is-danger', 'title' => 'Awww snap! That didn\'t work', 'alert' => sprintf('There was an error while saving your information. Please review the form, address all errors, and try again.', $model->name)];
            if (is_array($errors) && array_key_exists('schemaError', $errors)) {
                $alert->alert = $alert->alert.'<br>'.$errors['message'];
            }

            return redirect()
                ->to($previousUrl)
                ->with('isFromPost', true)
                ->with('item', $item)
                ->with('alert', $alert)
                ->with('metaId', $metaId)
                ->with('selectedTab', $selectedTab)
                ->with('errorMessages', $errorMessages)
                ->with('formStatus', 'error')
                ->withErrors($errors);
        }

        if ($isNew) {
            // Update meta that may have been created to point to this new item:
            $this->dataService->updateMetaIds($metaId, $item->id);
            $redirectRouteParams['id'] = $item->id;
            $previousUrl = preg_replace('/\/new\b/', '/'.$item->id, $previousUrl);
        }

        return redirect()
            ->to($previousUrl)
            ->with('selectedTab', $selectedTab)
            ->with('alert', (object) [
                'class' => 'is-success',
                'title' => 'Success!',
                'alert' => sprintf('The %s was successfully %s. Continue editing or&nbsp;<a class="has-underline" href="%s">go back to the %s</a>.',
                    $model->name,
                    $id == 'new' ? 'created' : 'updated',
                    data_get($model, 'isSingular') ? route('laramie::dashboard') : route('laramie::go-back', ['modelKey' => $modelKey]),
                    data_get($model, 'isSingular') ? 'dashboard' : 'list page'),
                ])
            ->with('formStatus', 'success')
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

        $fieldValidation = data_get($field, 'validation');
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

            case 'checkbox':
                return $value === '1';

            case 'boolean':
                if ($value === '1') {
                    return true;
                } elseif ($value === '0') {
                    return false;
                }

                return null;

            case 'markdown':
                return LaramieHelpers::getLaramieMarkdownObjectFromRawText($value);

            case 'password':
                if ($request->get('_'.$fieldName)) {
                    // The 'keep' checkbox was checked. Remove the "required" validation if we're keeping something that already exists.
                    $this->validationRules[$fieldName] = collect(explode('|', $fieldValidation))
                        ->filter(function($item) {
                            return $item !== 'required';
                        })
                        ->join('|');

                    return (object) ['encryptedValue' => 'keep'];
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
                    //$this->dataService->removeFile(data_get($item, $fieldName));
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
                        data_get($field, 'isPublic', config('laramie.files_are_public_by_default', false)),
                        sprintf('%s.%s', $this->modelKey, $fieldName)
                    );
                } elseif ($request->get('_'.$fieldName)) {
                    // Remove the "required" validation if we're keeping something that already exists.
                    $this->validationRules[$fieldName] = collect(explode('|', $fieldValidation))
                        ->filter(function($item) {
                            return !preg_match('/^required/', $item);
                        })
                        ->join('|');
                    // The 'keep' checkbox was checked.
                    return $this->dataService->findByIdSuperficial('laramieUpload', $request->get('_'.$fieldName));
                } else {
                    // check to see if we need to remove an old file
                    //$this->dataService->removeFile(data_get($item, $fieldName));
                    return null;
                }
                break;

            case 'reference':
                $tmp = null;
                $uuid = $value;
                $tmpModel = $this->dataService->getModelByKey($field->relatedModel);
                if ($field->subtype == 'single' && $uuid && Str::isUuid($uuid)) {
                    // Single refs, non-array value of uuid
                    $tmp = $this->dataService->findById($tmpModel, $uuid);
                } else {
                    // Multi refs, array value of uuids
                    $tmp = collect(preg_split('/\s*[,|]\s*/', $uuid))
                        ->filter(function ($item) {
                            return $item && Str::isUuid($item);
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
                        $prefix = data_get($matches, 'prefix', null);
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
        $error = null;

        try {
            $this->dataService->deleteById($modelKey, $id);
        } catch (Exception $e) {
            $error = $e->getMessage();
        }

        if ($request->ajax()) {
            return response()->json(['success' => $error === null, 'message' => $error ?: 'ok']);
        }

        if ($error) {
            return redirect()->back()->with(['alert' => (object) ['class' => 'is-danger', 'title' => 'Error', 'alert' => $error]]);
        }

        return redirect()
            ->to(route('laramie::list', ['modelKey' => $modelKey]))
            ->with(['alert' => (object) ['class' => 'is-success', 'title' => 'Success', 'alert' => 'Item deleted']]);
    }

    /**
     * Endpoint to delete a historical revision.
     *
     * @return \Illuminate\Http\Response
     */
    public function deleteRevision($modelKey, $revisionId)
    {
        $model = $this->dataService->getModelByKey($item->type);

        if (config('laramie.disable_revisions') || data_get($model, 'disableRevisions')) {
            abort(403, 'Forbidden');
        }

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
        $model = $this->dataService->getModelByKey($item->type);

        if (config('laramie.disable_revisions') || data_get($model, 'disableRevisions')) {
            abort(403, 'Forbidden');
        }

        $item = $this->dataService->restoreRevision($revisionId);
        $alert = (object) ['class' => 'is-warning', 'title' => 'Revision loaded', 'alert' => sprintf('The revision from %s has been loaded successfully.', \Carbon\Carbon::parse($item->updated_at, config('laramie.timezone'))->toDayDateTimeString())];

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

        if (config('laramie.disable_revisions') || data_get($model, 'disableRevisions')) {
            abort(403, 'Forbidden');
        }

        $itemId = data_get($item, 'laramie_data_id');

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

        foreach (data_get($model, 'fields') as $key => $field) {
            $diff = null;

            switch ($field->type) {
                case 'computed':
                case 'password':
                    break;
                case 'aggregate': // Aggregates pose a bit of a challenge -- we could recurse into them... but doing a diff on their json encoded data is easier. I'm fine with that for now.
                case 'hidden': // Hidden fields can literally be anything (including objects, etc), so we're going to handle the same way we handle aggregates for now.
                    $diff = $differ->render(json_encode(data_get($previousItem, $key, '{}'), JSON_PRETTY_PRINT), json_encode(object_get($item, $key, '{}'), JSON_PRETTY_PRINT));
                    break;
                case 'file':
                case 'image':
                case 'reference':
                    $a = data_get($item, $key, '');
                    $a = collect(is_array($a) ? $a : [$a])
                        ->map(function ($e) {
                            // Depending on context, we may be getting an object (diffing from **current** `$item`), or a string (diffing two old items).
                            return gettype($e) == 'string' ? $e : data_get($e, 'id');
                        })
                        ->filter()
                        ->all();
                    $b = data_get($previousItem, $key, '');
                    $b = array_filter(is_array($b) ? $b : [$b]);

                    $aAliases = [];
                    $bAliases = [];
                    $tmpRelatedModel = $this->dataService->getModelByKey($field->relatedModel);

                    foreach ($a as $id) {
                        $tmp = $this->dataService->findById($field->relatedModel, $id);
                        $aAliases[] = data_get($tmp, object_get($tmpRelatedModel, 'alias', 'id'));
                    }

                    foreach ($b as $id) {
                        $tmp = $this->dataService->findById($field->relatedModel, $id);
                        $bAliases[] = data_get($tmp, object_get($tmpRelatedModel, 'alias', 'id'));
                    }

                    $diff = $differ->render(implode(', ', $bAliases), implode('', $aAliases));
                    break;
                case 'markdown':
                    $diff = $differ->render(data_get($previousItem, "$key.markdown", ''), object_get($item, "$key.markdown", ''));
                    break;
                case 'timestamp':
                    $previous = sprintf('%s %s %s', data_get($previousItem, "$key.date", ''), object_get($previousItem, "$key.time", ''), object_get($previousItem, "$key.timezone", ''));
                    $current = sprintf('%s %s %s', data_get($item, "$key.date", ''), object_get($item, "$key.time", ''), object_get($item, "$key.timezone", ''));
                    $diff = $differ->render($previous, $current);
                    break;
                default:
                    $diff = $differ->render(data_get($previousItem, $key, ''), object_get($item, $key, ''));
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
        $leftLabel = $hasPrevious ? sprintf('Item from %s', \Carbon\Carbon::parse($previousItem->updated_at, config('laramie.timezone'))->toDayDateTimeString()) : '--';
        $rightLabel = sprintf('Item from %s', \Carbon\Carbon::parse($item->updated_at, config('laramie.timezone'))->toDayDateTimeString());

        return view(request()->ajax() ? 'laramie::partials.revision-comparison-table' : 'laramie::revision-compare')
            ->with('model', $model)
            ->with('leftLabel', $leftLabel)
            ->with('rightLabel', $rightLabel)
            ->with('diffs', $diffs);
    }
}
