<?php

namespace Laramie\Listeners;

use DB;
use Cache;
use Exception;
use Illuminate\Http\File;
use Ramsey\Uuid\Uuid;
use Storage;
use Str;

use Laramie\AdminModels\LaramieAlert;
use Laramie\Globals;
use Laramie\Hook;
use Laramie\Lib\LaramieHelpers;
use Laramie\Lib\LaramieModel;
use Laramie\Lib\ModelLoader;
use Laramie\Services\LaramieDataService;

class LaramieListener
{
    /**
     * Register the listeners for the subscriber.
     *
     * @param Illuminate\Events\Dispatcher $events
     */
    public function subscribe($events)
    {
        // By default, laramie's listeners will be fired after others (by default,
        // listeners are added with a sort of zero, which will run _before_ Laramie's).
        Hook::listen('Laramie\Hooks\ConfigLoaded', 'Laramie\Listeners\LaramieListener@configLoaded', 1);
        Hook::listen('Laramie\Hooks\FilterQuery', 'Laramie\Listeners\LaramieListener@filterQuery', 1);
        Hook::listen('Laramie\Hooks\PostFetch', 'Laramie\Listeners\LaramieListener@postFetch', 1);
        Hook::listen('Laramie\Hooks\PreEdit', 'Laramie\Listeners\LaramieListener@preEdit', 1);
        Hook::listen('Laramie\Hooks\PreSave', 'Laramie\Listeners\LaramieListener@preSave', 1);
        Hook::listen('Laramie\Hooks\PostSave', 'Laramie\Listeners\LaramieListener@postSave', 1);
        Hook::listen('Laramie\Hooks\HandleBulkAction', 'Laramie\Listeners\LaramieListener@handleBulkAction', 1);
        Hook::listen('Laramie\Hooks\PreDelete', 'Laramie\Listeners\LaramieListener@preDelete', 1);
    }

    /**
     * Handle config-loaded event
     *
     * @param $event Laramie\Hooks\ConfigLoaded
     */
    public function configLoaded($event)
    {
        $config = $event->config;

        $laramieRoleModel = data_get($config, 'models.laramieRole');

        $models = collect($config->models);

        Cache::forget(Globals::LARAMIE_TYPES_CACHE_KEY);

        $nonSystemModels = $models
            ->filter(function ($e) {
                return !data_get($e, 'isSystemModel');
            })
            ->sortBy(function ($e) {
                return $e->namePlural;
            });

        // Tweak laramie upload preview to reference appropriate admin url:
        $laramieUpload = data_get($models, 'laramieUpload');
        $laramieUpload->fields->preview->sql = str_replace('_admin_url_', config('laramie.admin_url'), $laramieUpload->fields->preview->sql);

        foreach ($nonSystemModels as $nonSystemModel) {
            $showName = data_get($nonSystemModel, 'isSingular', false) ? $nonSystemModel->name : $nonSystemModel->namePlural;
            $laramieRoleModel->fields->{$nonSystemModel->_type} = ModelLoader::processField($nonSystemModel->_type, (object) ['type' => 'select', 'label' => 'Can manage '.$showName, 'isMultiple' => true, 'isSelect2' => true, 'options' => [['All abilities', 'all'], ['List & View', 'read'], ['Create', 'create'], ['Update', 'update'], ['Delete', 'delete']]]);
        }

        if (!config('laramie.disable_meta')) {
            foreach ($models as $model) {
                if (data_get($model, 'disableMeta')) {
                    continue;
                }
                $model->fields->_tags = ModelLoader::processField('_tags', (object) ['type' => 'computed', 'isMetaField' => true, 'isDeferred' => true, 'sql' => '(select count(*) from laramie_data as ld2 where ld2.type = \'laramieTag\' and (ld2.data->>\'relatedItemId\')::uuid = laramie_data.id)', 'listByDefault' => false, 'isSearchable' => false]);
                $model->fields->_comments = ModelLoader::processField('_comments', (object) ['type' => 'computed', 'isMetaField' => true, 'isDeferred' => true, 'sql' => '(select count(*) from laramie_data as ld2 where ld2.type = \'laramieComment\' and (ld2.data->>\'relatedItemId\')::uuid = laramie_data.id)', 'listByDefault' => false, 'isSearchable' => false]);
                $model->fields->_versions = ModelLoader::processField('_versions', (object) ['type' => 'computed', 'isMetaField' => true, 'isDeferred' => true, 'sql' => '(select count(*) from laramie_data_archive as lda where laramie_data.id = lda.laramie_data_id)', 'listByDefault' => false, 'isSearchable' => false]);
            }
        }
    }

    /**
     * Handle filter query event.
     *
     * Only show system roles to admins on list page.
     *
     * @param $event Laramie\Hooks\FilterQuery
     */
    public function filterQuery($event)
    {
        $model = $event->model;
        $query = $event->query;
        $user = $event->user;
        $type = $model->_type;

        switch ($type) {
            case 'laramieRole':
                // The only way we can hit preFetch and not have a user is on
                // authentication -- we don't need to worry about limiting the
                // query by the user in this case -- it's just to get the list of
                // their roles.
                if ($user !== null && !optional($user)->isAdmin()) {
                    $query->whereNotIn('id', [Globals::AdminRoleId]);
                }
                break;
            case 'laramieAlert':
                // Only show the messages for which the user is the recipient:
                $query->where(function ($query) use ($user) {
                    $query->where(DB::raw('data->>\'recipient_id\''), '=', optional($user)->id ?: -1);
                });
                break;
            case 'profile':
            case 'laramieUser':
                // `laramieUser` is a stand-in, there shouldn't' be any `laramieUser` records in the db. Union information from the user's table so we can make it work.
                // @todo -- we may be able to make this less fragile. inspect output from dd($query).columns, etc
                $userQuery = DB::table('users')
                    ->addSelect(DB::raw('uuid_generate_v3(uuid_ns_url(), id::text) as id'))
                    ->addSelect(DB::raw('id as user_id'))
                    ->addSelect(DB::raw('\'' . $type . '\' as type'))
                    ->addSelect(DB::raw('data || concat(\'{"user":"\','.config('laramie.username').',\'"}\')::jsonb as data'))
                    ->addSelect('created_at')
                    ->addSelect('updated_at')
                    ->addSelect(DB::raw('uuid_generate_v3(uuid_ns_url(), id::text)::text as _id'))
                    ->addSelect(DB::raw('created_at as _created_at'))
                    ->addSelect(DB::raw('updated_at as _updated_at'));

                // Where conditions and order bys added to the laramieUser query have to be copied and translated to work with the users table:

                // Copy the order bys
                foreach (data_get($query, 'orders', []) as $order) {
                    if (optional(data_get($order, 'column'))->getValue() === 'data #>> \'{"user"}\'') {
                        $userQuery->orderBy(config('laramie.username'), data_get($order, 'direction'));
                    } else {
                        $userQuery->orderBy(data_get($order, 'column'), data_get($order, 'direction'));
                    }
                }

                // Copy the wheres:
                $wheres = [];
                $bindings = [];
                $queryWheres = $query->wheres;
                $queryBindings = $query->getBindings();
                for ($i = 0; $i < count($queryWheres); $i ++) {
                    if (data_get($queryWheres, $i . '.column')) {
                        continue;
                    }
                    if (optional(data_get($queryWheres, $i . '.query.wheres.0.column'))->getValue() === 'data->>\'user\'') {
                        $userQuery->where(config('laramie.username'), data_get($queryWheres, $i . '.query.wheres.0.operator'), $queryBindings[$i]);
                    }
                    else {
                        $wheres[] = $queryWheres[$i];
                        $bindings[] = $queryBindings[$i];
                    }
                }
                $userQuery->mergeWheres($wheres, $bindings);

                // If filtering by id, translate it to work for the users table
                $whereId = collect($queryWheres)->filter(function($item) { return data_get($item, 'column') === 'id'; })->first();
                if ($whereId) {
                    $userQuery->where(DB::raw('uuid_generate_v3(uuid_ns_url(), id::text)::text'), data_get($whereId, 'value'));
                }

                $query->unionAll($userQuery);

                break;
        }
    }

    /**
     * Handle pre-list event.
     *
     * Only show system roles to admins on list page.
     *
     * @param $event Laramie\Hooks\preList
     */
    public function postFetch($event)
    {
        $model = $event->model;
        $items = $event->items;
        $user = $event->user;
        $type = $model->_type;

        if ($items->count() === 0) {
            return;
        }

        $deferredFields = collect($model->fields)
            ->filter(function($item) {
                return data_get($item, 'type') === 'computed' && data_get($item, 'isDeferred');
            });

        if (count($deferredFields)) {
            $valuesQuery = DB::table('laramie_data')
                ->addSelect('id')
                ->whereIn('id', $items->pluck('id'));

            foreach ($deferredFields as $deferredFieldKey => $deferredField) {
                $valuesQuery->addSelect(DB::raw($deferredField->sql . ' as "' . $deferredFieldKey . '"'));
            }

            $deferredValues = $valuesQuery->get()->keyBy('id');

            foreach ($deferredFields as $deferredFieldKey => $deferredField) {
                foreach ($items as $item) {
                    $item->{$deferredFieldKey} = data_get($deferredValues, $item->id . '.' . $deferredFieldKey);
                }
            }
        }
    }

    /**
     * Handle pre-edit event.
     *
     * Prevent system roles from being edited.
     *
     * @param $event Laramie\Hooks\PreEdit
     */
    public function preEdit($event)
    {
        $model = $event->model;
        $item = $event->item;
        $user = $event->user;
        $extra = $event->extra;
        $type = $model->_type;

        switch ($type) {
            case 'laramieRole':
                // Don't allow main system rles to be edited
                if (in_array($item->id, [Globals::AdminRoleId])) {
                    throw new Exception('Sorry, you may not edit default system roles.');
                }
                break;
            case 'profile':
            case 'laramieUser':
                $laravelUser = DB::table('users')->find($item->user_id);
                $item->password = LaramieHelpers::getLaramiePasswordObjectFromPasswordText('password');
                break;
        }

        $dataService = $this->getLaramieDataService();
        $refs = data_get($model, 'refs', []);
        if ($refs) {
            $model->refs = collect($refs)
                ->map(function($item) use($dataService) {
                    $relatedModel = $dataService->getModelByKey($item->model);
                    return (object) [
                        'label' => data_get($item, 'label', data_get($relatedModel, 'namePlural')),
                        'type' => data_get($relatedModel, '_type'),
                        'alias' => data_get($relatedModel, 'alias'),
                        'field' => data_get($item, 'throughField'),
                        'quickSearch' => implode(', ', data_get($relatedModel, 'quickSearch')),
                    ];
                });
        }
    }

    /**
     * Handle bulk actions.
     *
     * @param $event Laramie\Hooks\HandleBulkAction
     */
    public function handleBulkAction($event)
    {
        $model = $event->model;
        $nameOfBulkAction = $event->nameOfBulkAction;
        $items = $event->items;
        $user = $event->user;
        $extra = $event->extra;
        $type = $model->_type;

        $dataService = $this->getLaramieDataService();

        $bulkActionName = Str::slug($nameOfBulkAction);

        if ($bulkActionName === 'delete') {
            foreach ($items as $item) {
                $dataService->deleteById($model, $item->id);
            }
        }

        else if ($bulkActionName === 'duplicate') {
            foreach ($items as $item) {
                $newItem = $item->replicate();
                $dataService->save($model, $newItem);
            }
        }

        else if ($bulkActionName === 'export-to-csv') {
            $listableFields = data_get($extra, 'listableFields', collect(['id'])) // should always be defined, but default to `id` just in case
                ->filter(function ($item) { // Don't include meta fields in export (versions, tags, comments).
                    return data_get($item, 'isMetaField') !== true;
                });

            $csvData = [];
            $csvHeaders = [];
            $csvFieldOrder = [];
            foreach ($listableFields as $key => $field) {
                $csvHeaders[] = $field->label;
                $csvFieldOrder[$key] = $field;
            }

            $csvData[] = $csvHeaders;

            foreach ($items as $record) {
                $csvOutput = [];
                foreach ($csvFieldOrder as $key => $field) {
                    $value = data_get($record, $key);
                    $csvOutput[] = LaramieHelpers::formatListValue($field, $value, false);
                }
                $csvData[] = $csvOutput;
            }

            $outputFile = storage_path(Uuid::uuid4()->toString().'.csv');
            $writer = \League\Csv\Writer::createFromPath($outputFile, 'w+');
            $writer->insertAll($csvData);
            $extra->response = response()->download($outputFile, sprintf('%s_%s.csv', Str::snake($model->namePlural), date('Ymd')))->deleteFileAfterSend(true);
        }
    }

    /**
     * Handle pre-save event -- MUST be synchronous -- enables ability to
     * throw exceptions for custom validation rules, etc and to modify data
     * before saving.
     *
     * @param $event Laramie\Hooks\PreSave
     */
    public function preSave($event)
    {
        $model = $event->model;
        $item = $event->item;
        $user = $event->user;
        $type = $model->_type;

        $dataService = $this->getLaramieDataService();
        $dataService->clearCache();

        switch ($type) {
            // Create thumbnails for images
            case 'laramieUpload':
                LaramieHelpers::postProcessLaramieUpload($item);
                break;

            case 'profile':
                $userInfoToUpdate = [
                    config('laramie.username') => $item->user,
                    'updated_at' => \Carbon\Carbon::now(),
                ];
                $hashedPassword = data_get($item, 'password.encryptedValue');
                if ($hashedPassword && $hashedPassword !== 'keep') {
                    $userInfoToUpdate['password'] = $item->password->encryptedValue;
                }

                DB::table('users')
                    ->where('id', $item->user_id)
                    ->update($userInfoToUpdate);
                break;

            case 'laramieUser':
                // Update the underlying laravel user:
                $userInfoToUpdate = [
                    config('laramie.username') => $item->user,
                    'updated_at' => \Carbon\Carbon::now(),
                ];

                $hashedPassword = data_get($item, 'password.encryptedValue');
                if ($hashedPassword && $hashedPassword !== 'keep') {
                    $userInfoToUpdate['password'] = $item->password->encryptedValue;
                }

                $userRecord = DB::table('users')->where('id', $item->user_id)->first();
                $jsonInfo = json_decode(data_get($userRecord, 'data'));

                $modelFields = data_get($model, 'fields');
                foreach ($modelFields as $fieldName => $field) {
                    // Don't store dummy info
                    if (
                        strpos($fieldName, '_') === 0 ||
                        in_array($fieldName, ['user', 'password'])
                    ) {
                        continue;
                    }
                    // We're not going through the main service for saving, so we need to do some finagling here:
                    if ($field->type === 'reference') {
                        if ($field->subtype === 'many') {
                            $jsonInfo->{$fieldName} = collect(data_get($item, $fieldName))
                                ->map(function($item) { return data_get($item, 'id'); })
                                ->toArray();
                        }
                        else {
                            $jsonInfo->{$fieldName} = data_get($item, $fieldName . '.id');
                        }
                    }
                    else {
                        $jsonInfo->{$fieldName} = data_get($item, $fieldName);
                    }
                }

                $userInfoToUpdate['data'] = json_encode($jsonInfo);

                DB::table('users')
                    ->where('id', $item->user_id)
                    ->update($userInfoToUpdate);

                break;
        }
    }

    /**
     * Handle post-save event.
     *
     * @param $event Laramie\Hooks\PostSave
     */
    public function postSave($event)
    {
        $model = $event->model;
        $item = $event->item;
        $user = $event->user;
        $type = $model->_type;

        $dataService = $this->getLaramieDataService();
        $dataService->clearCache();

        switch ($type) {
            // Create thumbnails for images?
            case 'laramieUpload':
                break;
            case 'laramieComment':
                $plainText = data_get($item, 'comment.markdown');
                preg_match_all('/@(?<mentions>[a-z0-9\-\.\_@]+)/i', $plainText, $matches);
                $mentions = data_get($matches, 'mentions');
                foreach ($mentions as $mention) {
                    $mentionedUser = DB::table('users')
                        ->where(config('laramie.username'), 'ilike', $mention . '%')
                        ->first();
                    if ($mentionedUser) {
                        LaramieAlert::create([
                            'metaItemId' => data_get($item, 'id'),
                            'recipient_id' => $mentionedUser->id,
                            'author_id' => optional($user)->id,
                            'message' => $item->comment,
                            'status' => 'Unread',
                        ]);
                    }
                }
        }
    }

    /**
     * Handle pre-delete.
     *
     * @param $event Laramie\Hooks\PostSave
     */
    public function preDelete($event)
    {
        $model = $event->model;
        $item = $event->item;

        if (in_array(data_get($item, 'id'), [Globals::AdminRoleId])) {
            throw new Exception('You may not delete one of the core roles.');
        }

        if (data_get($model, 'isDeletable') === false) {
            throw new Exception('Items of this type may not be deleted');
        }
    }

    /**
     * Return the Laramie data service.
     *
     * @return Laramie\Services\LaramieDataService
     */
    private function getLaramieDataService()
    {
        return app(LaramieDataService::class);
    }

}
