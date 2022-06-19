<?php

declare(strict_types=1);

namespace Laramie\Listeners;

use Cache;
use DB;
use Exception;
use Illuminate\Http\File;
use Laramie\AdminModels\LaramieAlert;
use Laramie\Globals;
use Laramie\Hook;
use Laramie\Lib\LaramieHelpers;
use Laramie\Lib\LaramieModel;
use Laramie\Services\LaramieDataService;
use Ramsey\Uuid\Uuid;
use Str;

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
     * Handle config-loaded event.
     *
     * @param $event Laramie\Hooks\ConfigLoaded
     */
    public function configLoaded($event)
    {
        $config = $event->config;

        $laramieRoleModel = data_get($config, 'models.laramieRole');

        $models = $config->models;

        Cache::forget(Globals::LARAMIE_TYPES_CACHE_KEY);

        $nonSystemModels = $models
            ->filter(function ($item) {
                return $item->isSystemModel() !== true;
            })
            ->sortBy(function ($e) {
                return $e->getNamePlural();
            });

        // Tweak laramie upload preview to reference appropriate admin url:
        $laramieUpload = data_get($models, 'laramieUpload');
        $laramieUpload->getFieldSpec('preview')->set('sql', str_replace('_admin_url_', config('laramie.admin_url'), $laramieUpload->getFieldSpec('preview')->getSql()));

        foreach ($nonSystemModels as $nonSystemModel) {
            $showName = data_get($nonSystemModel, 'isSingular', false) ? $nonSystemModel->getName() : $nonSystemModel->getNamePlural();
            $laramieRoleModel->addField($nonSystemModel->getType(), (object) ['type' => 'select', 'label' => 'Can manage '.$showName, 'isMultiple' => true, 'isSelect2' => true, 'options' => [['All abilities', 'all'], ['List & View', 'read'], ['Create', 'create'], ['Update', 'update'], ['Delete', 'delete']]]);
        }

        if (!config('laramie.disable_meta')) {
            foreach ($models as $model) {
                if ($model->isDisableMeta()) {
                    continue;
                }
                $model->addField('_tags', (object) ['type' => 'computed', 'isMetaField' => true, 'isDeferred' => true, 'sql' => '(select count(*) from laramie_data as ld2 where ld2.type = \'laramieTag\' and (ld2.data->>\'relatedItemId\')::uuid = laramie_data.id)', 'isListByDefault' => false, 'isSearchable' => false, 'listTemplate' => '<span class="js-meta" data-meta-type="tags"><i class="fas fa-tags has-text-grey"></i>&nbsp;<span class="tag-count">{{value}}</span></span>']);
                $model->addField('_comments', (object) ['type' => 'computed', 'isMetaField' => true, 'isDeferred' => true, 'sql' => '(select count(*) from laramie_data as ld2 where ld2.type = \'laramieComment\' and (ld2.data->>\'relatedItemId\')::uuid = laramie_data.id)', 'isListByDefault' => false, 'isSearchable' => false, 'listTemplate' => '<span class="js-meta" data-meta-type="comments"><i class="fas fa-comments has-text-grey"></i>&nbsp;<span class="comment-count">{{value}}</span></span>']);
                $model->addField('_versions', (object) ['type' => 'computed', 'isMetaField' => true, 'isDeferred' => true, 'sql' => '(select count(*) from laramie_data_archive as lda where laramie_data.id = lda.laramie_data_id)', 'isListByDefault' => false, 'isSearchable' => false]);
            }
        }

        // Find all models within the auto-discovery folder that extend LaramieModel
        $modelDiscoveryPaths = config('laramie.model_discovery_path', []);
        $modelDiscoveryPaths = array_filter(is_array($modelDiscoveryPaths) ? $modelDiscoveryPaths : [$modelDiscoveryPaths]);
        $factoryHash = [];
        foreach ($modelDiscoveryPaths as $path) {
            $directory = new \RecursiveDirectoryIterator($path);
            $iterator = new \RecursiveIteratorIterator($directory);
            $phpFiles = new \RegexIterator($iterator, '/^.+\.php$/i', \RecursiveRegexIterator::GET_MATCH);
            foreach ($phpFiles as $info) {
                $filePath = data_get($info, 0);
                $code = file($filePath);
                $namespace = null;
                $className = null;
                $jsonClassFallback = null;

                // Extract the class's namespace:
                foreach ($code as $line) {
                    if (preg_match('/^\s*namespace\s+(?<namespace>[^;]+);/', $line, $match)) {
                        $namespace = data_get($match, 'namespace');
                        break;
                    }
                }
                // Extract the class's name:
                foreach ($code as $line) {
                    if (preg_match('/^\s*class\s+(?<className>[^\W]+)/', $line, $match)) {
                        $className = data_get($match, 'className');
                        break;
                    }
                }

                // Extract the json class's name (will only be used as a fall-back if we can't get it via reflection).
                foreach ($code as $line) {
                    if (preg_match('/[$]jsonClass\s*=\s*(?<jsonClass>[^;]+);/', $line, $match)) {
                        $jsonClassFallback = preg_replace('/[\'"]/', '', data_get($match, 'jsonClass'));
                        break;
                    }
                }

                // Using the namespace and class name, use reflection to determine if the file is a subclass of `LaramieModel`
                if ($namespace && $className) {
                    try {
                        $fullPath = implode('\\', [$namespace, $className]);
                        $reflectionClass = new \ReflectionClass($fullPath);

                        if ($reflectionClass->isSubclassOf(LaramieModel::class)) {
                            // try to get the json class via reflection (might be different than convention).
                            $jsonClassFallback = $jsonClassFallback ?: lcfirst($className); // make a note in documentation that convention looks for camel-cased json name
                            $jsonClass = null;
                            try {
                                $tmp = $reflectionClass->newInstanceWithoutConstructor();
                                $jsonClass = $tmp->getJsonClass();
                            } catch (\Exception $e) {
                                $jsonClass = $jsonClassFallback;
                            }

                            $factoryHash[$jsonClass] = $reflectionClass->getName();
                        }
                    } catch (\Exception $e) {
                        Log::error("Laramie: could not use reflection to determine ancestry for $fullPath");
                    }
                }
            }
        }

        if (count($factoryHash)) {
            foreach ($models as $model) {
                if ($model->getFactory() !== LaramieModel::class) {
                    continue;
                }
                if (array_key_exists($model->getType(), $factoryHash)) {
                    $model->set('factory', $factoryHash[$model->getType()]);
                }
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
        $extra = $event->extra;
        $type = $model->getType();

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
                $userQuery = DB::table('users')
                    ->addSelect(DB::raw('uuid_generate_v3(uuid_ns_url(), id::text) as id'))
                    ->addSelect(DB::raw('id as user_id'))
                    ->addSelect(DB::raw('\''.$type.'\' as type'))
                    ->addSelect(DB::raw('data || concat(\'{"user":"\','.config('laramie.username').',\'"}\')::jsonb as data'))
                    ->addSelect('created_at')
                    ->addSelect('updated_at');

                $additionalSelects = collect($query->columns)
                    ->filter(function ($item) {
                        return $item instanceof \Illuminate\Database\Query\Expression;
                    });

                foreach ($additionalSelects as $item) {
                    $condition = $item->getValue();
                    if (strpos($condition, 'id::text') !== false) {
                        $userQuery->addSelect(DB::raw(str_replace('id::text', 'uuid_generate_v3(uuid_ns_url(), id::text)::text', $condition)));
                    } else {
                        $userQuery->addSelect($item);
                    }
                }

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
                for ($i = 0; $i < count($queryWheres); $i++) {
                    if (data_get($queryWheres, $i.'.column')) {
                        break;
                    }
                    if (strpos(optional(data_get($queryWheres, $i.'.query.wheres.0.column'))->getValue(), 'data->>\'user\'') !== false) {
                        $userQuery->where(config('laramie.username'), data_get($queryWheres, $i.'.query.wheres.0.operator'), $queryBindings[$i]);
                    } else {
                        $wheres[] = $queryWheres[$i];
                        $bindings[] = $queryBindings[$i];
                    }
                }
                $userQuery->mergeWheres($wheres, $bindings);

                // If filtering by id, translate it to work for the users table
                if (!(data_get($extra, 'isFromAjaxController') && data_get($extra, 'isInvertedSearch'))) {
                    $whereId = collect($queryWheres)->filter(function ($item) { return data_get($item, 'column') === 'id'; })->first();
                    if ($whereId) {
                        if (strtolower(data_get($whereId, 'type', '')) === 'in') {
                            $userQuery->whereIn(DB::raw('uuid_generate_v3(uuid_ns_url(), id::text)::text'), data_get($whereId, 'values'));
                        } else {
                            $userQuery->where(DB::raw('uuid_generate_v3(uuid_ns_url(), id::text)::text'), data_get($whereId, 'operator', '='), data_get($whereId, 'value'));
                        }
                    }
                }

                $query->unionAll($userQuery);

                break;
        }
    }

    /**
     * Handle PostFetch event.
     *
     * @param $event Laramie\Hooks\PostFetch
     */
    public function postFetch($event)
    {
        $model = $event->model;
        $items = $event->items;
        $user = $event->user;
        $type = $model->getType();

        if ($items->count() === 0) {
            return;
        }

        $deferredFields = collect($model->getFieldsSpecs())
            ->filter(function ($item) {
                return $item->getType() === 'computed' && $item->isDeferred();
            });

        if (count($deferredFields)) {
            $valuesQuery = DB::table('laramie_data')
                ->addSelect('id')
                ->whereIn('id', $items->pluck('id'));

            foreach ($deferredFields as $deferredFieldKey => $deferredField) {
                $valuesQuery->addSelect(DB::raw($deferredField->getSql().' as "'.$deferredFieldKey.'"'));
            }

            $deferredValues = $valuesQuery->get()->keyBy('id');

            foreach ($deferredFields as $deferredFieldKey => $deferredField) {
                foreach ($items as $item) {
                    $item->{$deferredFieldKey} = data_get($deferredValues, $item->id.'.'.$deferredFieldKey);
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
        $type = $model->getType();

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
        $type = $model->getType();

        $dataService = $this->getLaramieDataService();

        $bulkActionName = Str::slug($nameOfBulkAction);

        if ($bulkActionName === 'delete') {
            foreach ($items as $item) {
                $item->delete();
            }
        } elseif ($bulkActionName === 'duplicate') {
            foreach ($items as $item) {
                $newItem = $item->replicate();
                $dataService->save($model, $newItem);
            }
        } elseif ($bulkActionName === 'export-to-csv') {
            $listableFields = data_get($extra, 'listableFields', collect(['id'])) // should always be defined, but default to `id` just in case
                ->filter(function ($item) { // Don't include meta fields in export (versions, tags, comments).
                    return data_get($item, 'isMetaField') !== true;
                });

            $csvData = [];
            $csvHeaders = [];
            $csvFieldOrder = [];
            foreach ($listableFields as $key => $field) {
                $csvHeaders[] = $field->getLabel();
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
            $extra->response = response()->download($outputFile, sprintf('%s_%s.csv', Str::snake($model->getNamePlural()), date('Ymd')))->deleteFileAfterSend(true);
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
        $type = $model->getType();

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

                $modelFields = $model->getFieldsSpecs();
                foreach ($modelFields as $fieldName => $field) {
                    // Don't store dummy info
                    if (
                        strpos($fieldName, '_') === 0 ||
                        in_array($fieldName, ['user', 'password'])
                    ) {
                        continue;
                    }
                    // We're not going through the main service for saving, so we need to do some finagling here:
                    if ($field->getType() === 'reference') {
                        if ($field->getSubtype() === 'many') {
                            $jsonInfo->{$fieldName} = collect(data_get($item, $fieldName))
                                ->map(function ($item) { return data_get($item, 'id'); })
                                ->toArray();
                        } else {
                            $jsonInfo->{$fieldName} = data_get($item, $fieldName.'.id');
                        }
                    } else {
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
        $type = $model->getType();

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
                        ->where(config('laramie.username'), 'ilike', $mention.'%')
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

        if (!$model->isDeletable()) {
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
