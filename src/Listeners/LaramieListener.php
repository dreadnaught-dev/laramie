<?php

namespace Laramie\Listeners;

use DB;
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
        Hook::listen('Laramie\Hooks\PostList', 'Laramie\Listeners\LaramieListener@postList', 1);
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
            $laramieRoleModel->fields->{$nonSystemModel->_type} = ModelLoader::processField($nonSystemModel->_type, (object) ['type' => 'boolean', 'label' => 'Can manage '.$showName]);
        }

        if (!config('laramie.disable_meta')) {
            foreach ($models as $model) {
                if (data_get($model, 'disableMeta')) {
                    continue;
                }
                $model->fields->_tags = ModelLoader::processField('_tags', (object) ['type' => 'computed', 'isDeferred' => true, 'sql' => '(select count(*) from laramie_data as ld2 where ld2.type = \'laramieTag\' and (ld2.data->>\'relatedItemId\')::uuid = laramie_data.id)']);
                $model->fields->_comments = ModelLoader::processField('_comments', (object) ['type' => 'computed', 'isDeferred' => true, 'sql' => '(select count(*) from laramie_data as ld2 where ld2.type = \'laramieComment\' and (ld2.data->>\'relatedItemId\')::uuid = laramie_data.id)']);
            }
        }
    }

    /**
     * Handle pre-list event.
     *
     * Only show system roles to admins on list page.
     *
     * @param $event Laramie\Hooks\preList
     */
    public function filterQuery($event)
    {
        $model = $event->model;
        $query = $event->query;
        $user = $event->user;
        $type = $model->_type;

        return;

        switch ($type) {
            case 'laramieRole':
                // The only way we can hit preFetch and not have a user is on
                // authentication -- we don't need to worry about limiting the
                // query by the user in this case -- it's just to get the list of
                // their roles.
                if ($user !== null && !$user->isAdmin()) {
                    $query->whereNotIn('id', [Globals::AdminRoleId]);
                }
                break;
            case 'laramieAlert':
                // Only show the messages for which the user is the recipient:
                $query->where(function ($query) use ($user) {
                    $query->where(DB::raw('data->>\'recipient_id\''), '=', optional($user)->id ?: -1);
                });
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

        $deferredFields = collect($model->fields)
            ->filter(function($item) {
                return data_get($item, 'type') === 'computed' && data_get($item, 'isDeferred');
            });

        if ($items->count() === 0) {
            return;
        }

        foreach ($deferredFields as $deferredFieldKey => $deferredField) {
            $values = DB::table('laramie_data')
                ->addSelect('id')
                ->addSelect(DB::raw($deferredField->sql . ' as val'))
                ->whereIn('id', $items->pluck('id'))
                ->get()
                ->keyBy('id');

            foreach ($items as $item) {
                $item->{$deferredFieldKey} = data_get($values, $item->id . '.val');
            }
        }
    }

    /**
     * Handle post-list event.
     *
     * @param $event Laramie\Hooks\PostList
     */
    public function postList($event)
    {
        $model = $event->model;
        $items = $event->items;
        $user = $event->user;
        $extra = $event->extra;

        $listFields = data_get($extra, 'listFields');

        $ids = [];

        foreach ($items as $item) {
            $ids[] = $item->id;
        }

        $dataService = $this->getLaramieDataService();

        $systemMetaFields = [
            '_versions' => function() use($dataService, $ids) { return $dataService->getNumVersions($ids); },
        ];

        foreach ($systemMetaFields as $metaField => $countGeneratorCallback) {
            $counts = null;
            $map = [];
            if (data_get($listFields, $metaField)) {
                $counts = $countGeneratorCallback();
                foreach ($counts as $count) {
                    $map[$count->laramie_data_id] = $count;
                }
                foreach ($items as $item) {
                    $count = data_get($map, $item->id, null);
                    $item->{$metaField} = str_replace('{*count*}', data_get($count, 'count', 0), $item->{$metaField});
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
        $type = $model->_type;

        switch ($type) {
            case 'laramieRole':
                // Don't allow main system rles to be edited
                if (in_array($item->id, [Globals::AdminRoleId])) {
                    throw new Exception('Sorry, you may not edit default system roles.');
                }
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
        $query = $event->query;
        $postData = $event->postData;
        $user = $event->user;
        $extra = $event->extra;
        $type = $model->_type;

        $dataService = $this->getLaramieDataService();

        $postData['quickSearch'] = data_get($postData, 'quick-search');

        // @note -- switching on the slugified version of the bulk action
        switch (Str::slug($nameOfBulkAction)) {
            case 'delete':
                // First create a backup of the items in the archive table
                $q1 = clone $query;
                $q1->select([DB::raw('uuid_generate_v1()'), 'id', DB::raw('\''.$dataService->getUserId().'\''), 'type', 'data', DB::raw('now()'), DB::raw('now()')]);
                DB::insert('insert into laramie_data_archive (id, laramie_data_id, user_id, type, data, created_at, updated_at)'.$q1->toSql(), $q1->getBindings());

                $q2 = clone $query;
                $q2->select(['id']);

                if ($type == 'laramieRole') {
                    $q2->whereNotIn('id', [Globals::AdminRoleId]); // don't allow deletion of core Laramie roles.
                }

                // Delete the items
                DB::statement('delete from laramie_data where id in ('.$q2->toSql().')', $q2->getBindings());
                break;

            case 'duplicate':
                $query->select([DB::raw('uuid_generate_v1()'), DB::raw('\''.$dataService->getUserId().'\''), 'type', 'data', DB::raw('now()'), DB::raw('now()')]);

                DB::insert('insert into laramie_data (id, user_id, type, data, created_at, updated_at) '.$query->toSql(), $query->getBindings());
                break;

            case 'export-to-csv':
                $itemIds = [];
                $listableFields = data_get($extra, 'listableFields', collect(['id'])) // should always be defined, but default to `id` just in case
                    ->filter(function ($item) { // Don't include meta fields in export (versions, tags, comments).
                        return data_get($item, 'isMetaField') !== true;
                    });

                // Have "all" matching records been selected? Great. But limit to `max_csv_records` just in case there are too many records
                $isAllSelected = data_get($postData, 'bulk-action-all-selected') === '1';
                if ($isAllSelected) {
                    $postData['resultsPerPage'] = config('laramie.max_csv_records');
                } else {
                    $itemIds = collect(data_get($postData, 'bulk-action-ids', []))
                        ->filter(function ($item) {
                            return $item && LaramieHelpers::isValidUuid($item);
                        });
                }

                $records = $dataService->findByType($model, $postData, function ($query) use ($itemIds) {
                    if ($itemIds) {
                        $query->whereIn(DB::raw('id::text'), $itemIds);
                    }
                });
                $csvData = [];
                $csvHeaders = [];
                $csvFieldOrder = [];
                foreach ($listableFields as $key => $field) {
                    $csvHeaders[] = $field->label;
                    $csvFieldOrder[$key] = $field;
                }
                $csvData[] = $csvHeaders;
                if ($isAllSelected && $records->hasMorePages()) {
                    $csvData[] = ['This report exceeds the maximum number of records to export ('.config('laramie.max_csv_records').'). Please filter or sort your data if other records are needed.'];
                }
                foreach ($records as $record) {
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
                break;
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
