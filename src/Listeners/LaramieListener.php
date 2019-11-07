<?php

namespace Laramie\Listeners;

use DB;
use Exception;
use Illuminate\Http\File;
use Intervention\Image\ImageManager;
use Ramsey\Uuid\Uuid;
use Storage;
use Laramie\Globals;
use Laramie\Services\LaramieDataService;
use Laramie\Lib\ModelLoader;
use Laramie\Lib\LaramieHelpers;
use Laramie\Lib\LaramieModel;

class LaramieListener
{
    /**
     * Register the listeners for the subscriber.
     *
     * @param Illuminate\Events\Dispatcher $events
     */
    public function subscribe($events)
    {
        $events->listen(
            'Laramie\Events\ConfigLoaded',
            'Laramie\Listeners\LaramieListener@configLoaded'
        );
        //$events->listen(
            //'Laramie\Events\PreList',
            //'Laramie\Listeners\LaramieListener@preList'
        //);
        $events->listen(
            'Laramie\Events\ShapeListQuery',
            'Laramie\Listeners\LaramieListener@shapeListQuery'
        );
        $events->listen(
            'Laramie\Events\PostList',
            'Laramie\Listeners\LaramieListener@postList'
        );
        $events->listen(
            'Laramie\Events\PreEdit',
            'Laramie\Listeners\LaramieListener@preEdit'
        );
        //$events->listen(
            //'Laramie\Events\TransformModelForEdit',
            //'Laramie\Listeners\LaramieListener@transformModelForEdit'
        //);
        $events->listen(
            'Laramie\Events\PreSave',
            'Laramie\Listeners\LaramieListener@preSave'
        );
        $events->listen(
            'Laramie\Events\PostSave',
            'Laramie\Listeners\LaramieListener@postSave'
        );
        $events->listen(
            'Laramie\Events\BulkAction',
            'Laramie\Listeners\LaramieListener@bulkAction'
        );
        $events->listen(
            'Laramie\Events\PreDelete',
            'Laramie\Listeners\LaramieListener@preDelete'
        );
    }

    /**
     * Handle config-loaded event
     *
     * @param $event Laramie\Events\ConfigLoaded
     */
    public function configLoaded($event)
    {
        $config = $event->config;

        $laramieRoleModel = object_get($config, 'models.LaramieRole');

        $nonSystemModels = collect($config->models)
            ->filter(function ($e) {
                return !object_get($e, 'isSystemModel');
            })
            ->sortBy(function ($e) {
                return $e->namePlural;
            });

        foreach ($nonSystemModels as $nonSystemModel) {
            $showName = object_get($nonSystemModel, 'isSingular', false) ? $nonSystemModel->name : $nonSystemModel->namePlural;
            $laramieRoleModel->fields->{$nonSystemModel->_type} = ModelLoader::processField($nonSystemModel->_type, (object) ['type' => 'boolean', 'label' => 'Can manage '.$showName]);
        }
    }

    /**
     * Handle pre-list event.
     *
     * Only show system roles to super admins on list page.
     *
     * @param $event Laramie\Events\ShapeListQuery
     */
    public function shapeListQuery($event)
    {
        $model = $event->model;
        $query = $event->query;
        $user = $event->user;
        $type = $model->_type;

        switch ($type) {
            case 'LaramieRole':
                // The only way we can hit shapeListQuery and not have a user is on
                // authentication -- we don't need to worry about limiting the
                // query by the user in this case -- it's just to get the list of
                // their roles.
                if ($user !== null && !($user->isSuperAdmin || $user->isAdmin)) {
                    $query->whereNotIn('id', [Globals::SuperAdminRoleId, Globals::AdminRoleId]);
                }
                break;
            case 'LaramieAlert':
                // Only show the messages for which the user is the recipient:
                if ($user !== null) {
                    $query->where(function ($query) use ($user) {
                        $query->where(DB::raw('data->>\'recipient\''), '=', $user->id);
                    });
                } elseif ($user === null) {
                    throw new Exception('You do not have access.');
                }
                break;
        }
    }

    /**
     * Handle post-list event.
     *
     * @param $event Laramie\Events\PostList
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
            '_comments' => function() use($dataService, $ids) { return $dataService->getNumComments($ids); },
            '_tags' => function() use($dataService, $ids) { return $dataService->getNumTags($ids); },
        ];

        foreach ($systemMetaFields as $metaField => $countGeneratorCallback) {
            $counts = null;
            $map = [];
            if (array_get($listFields, $metaField)) {
                $counts = $countGeneratorCallback();
                foreach ($counts as $count) {
                    $map[$count->laramie_data_id] = $count;
                }
                foreach ($items as $item) {
                    $count = array_get($map, $item->id, null);
                    $item->{$metaField} = str_replace('{*count*}', object_get($count, 'count', 0), $item->{$metaField});
                }
            }
        }
    }

    /**
     * Handle pre-edit event.
     *
     * Prevent system roles from being edited.
     *
     * @param $event Laramie\Events\PreEdit
     */
    public function preEdit($event)
    {
        $model = $event->model;
        $item = $event->item;
        $user = $event->user;
        $type = $model->_type;

        switch ($type) {
            case 'LaramieRole':
                // Don't allow main system rles to be edited
                if (in_array($item->id, [Globals::SuperAdminRoleId, Globals::AdminRoleId])) {
                    throw new Exception('Sorry, you may not edit default system roles (Super admin and User management.');
                }
                break;
            case 'LaramieUser':
                if (!object_get($item, 'api.username')) {
                    $item->api = (object) ['enabled' => false, 'username' => str_random(Globals::API_TOKEN_LENGTH), 'password' => str_random(Globals::API_TOKEN_LENGTH)];
                }
                break;
            case 'LaramieAlert':
                if ($item->_isNew) {
                    $model->fields->status->isEditable = false;
                    $model->fields->recipient->isEditable = true;
                } elseif (object_get($item, 'recipient.id') !== $user->id) {
                    $model->fields->status->type = 'hidden';
                } else {
                    $model->fields->_authorName->isEditable = true;
                    $model->fields->_authorName->type = 'hidden';
                }
                break;
        }
    }

    /**
     * Handle bulk actions.
     *
     * @param $event Laramie\Events\BulkAction
     */
    public function bulkAction($event)
    {
        $model = $event->model;
        $nameOfBulkAction = $event->nameOfBulkAction;
        $query = $event->query;
        $postData = $event->postData;
        $user = $event->user;
        $extra = $event->extra;
        $type = $model->_type;

        $dataService = $this->getLaramieDataService();

        $postData['quickSearch'] = array_get($postData, 'quick-search');

        // @note -- switching on the slugified version of the bulk action
        switch (str_slug($nameOfBulkAction)) {
            case 'delete':
                // First create a backup of the items in the archive table
                $q1 = clone $query;
                $q1->select([DB::raw('uuid_generate_v1()'), 'id', DB::raw('\''.$dataService->getUserUuid().'\''), 'type', 'data', DB::raw('now()'), DB::raw('now()')]);
                DB::insert('insert into laramie_data_archive (id, laramie_data_id, user_id, type, data, created_at, updated_at)'.$q1->toSql(), $q1->getBindings());

                $q2 = clone $query;
                $q2->select(['id']);

                if ($type == 'LaramieRole') {
                    $q2->whereNotIn('id', [Globals::SuperAdminRoleId, Globals::AdminRoleId]); // don't allow deletion of core Laramie roles.
                }

                // Delete the items
                DB::statement('delete from laramie_data where id in ('.$q2->toSql().')', $q2->getBindings());
                break;

            case 'duplicate':
                $query->select([DB::raw('uuid_generate_v1()'), DB::raw('\''.$dataService->getUserUuid().'\''), 'type', 'data', DB::raw('now()'), DB::raw('now()')]);

                DB::insert('insert into laramie_data (id, user_id, type, data, created_at, updated_at) '.$query->toSql(), $query->getBindings());
                break;

            case 'export-to-csv':
                $itemIds = [];
                $listableFields = object_get($extra, 'listableFields', collect(['id'])) // should always be defined, but default to `id` just in case
                    ->filter(function ($item) { // Don't include meta fields in export (versions, tags, comments).
                        return object_get($item, 'isMetaField') !== true;
                    });

                // Have "all" matching records been selected? Great. But limit to `max_csv_records` just in case there are too many records
                $isAllSelected = array_get($postData, 'bulk-action-all-selected') === '1';
                if ($isAllSelected) {
                    $postData['resultsPerPage'] = config('laramie.max_csv_records');
                } else {
                    $itemIds = collect(array_get($postData, 'bulk-action-ids', []))
                        ->filter(function ($item) {
                            return $item && Uuid::isValid($item);
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
                        $value = object_get($record, $key);
                        $csvOutput[] = LaramieHelpers::formatListValue($field, $value, false);
                    }
                    $csvData[] = $csvOutput;
                }
                $outputFile = storage_path(Uuid::uuid4()->toString().'.csv');
                $writer = \League\Csv\Writer::createFromPath($outputFile, 'w+');
                $writer->insertAll($csvData);
                $extra->response = response()->download($outputFile, sprintf('%s_%s.csv', snake_case($model->namePlural), date('Ymd')))->deleteFileAfterSend(true);
                break;
        }
    }

    /**
     * Handle pre-save event -- MUST be synchronous -- enables ability to
     * throw exceptions for custom validation rules, etc and to modify data
     * before saving.
     *
     * @param $event Laramie\Events\PreSave
     */
    public function preSave($event)
    {
        $model = $event->model;
        $item = $event->item;
        $user = $event->user;
        $type = $model->_type;

        // @note -- we're not running preSave commands for LaramieUsers or
        // LaramieUploads that are initiated by console commands -- right now the
        // only core console command that touches either is for authorizing users
        // (which takes care of what it needs).
        if (object_get($item, '_isFromConsole')) {
            return;
        }

        switch ($type) {
            case 'LaramieUser':
                // If we're saving a new user, we need to create a corresponding Laravel user
                if (object_get($item, '_isNew')) {
                    \DB::table('users')->insert([
                        'name' => $item->user,
                        'email' => $item->user,
                        'password' => $item->password->encryptedValue,
                        'created_at' => 'now()',
                        'updated_at' => 'now()',
                    ]);
                } else {
                    // If we're _updating_ a user, we need to grab its state _before_ the update (so that we can map it to its Laravel user).
                    $dataService = $this->getLaramieDataService();
                    $oldUserInfo = $dataService->findByIdSuperficial($dataService->getModelByKey('LaramieUser'), $item->id);
                    \DB::table('users')
                        ->where(config('laramie.username'), $oldUserInfo->user)
                        ->update([
                            'name' => $item->user,
                            'email' => $item->user,
                            'password' => $item->password->encryptedValue,
                            'updated_at' => 'now()',
                        ]);
                }
                break;
            // Create thumbnails for images
            case 'LaramieUpload':
                try {
                    // @optimize -- move thumb gen to postsave
                    // If the item is an image, create thumbnails (for use by the admin)
                    $storageDisk = config('laramie.storage_disk');
                    if ($item->extension && in_array($item->extension, ['jpeg', 'jpg', 'png', 'gif'])) { // only try to take thumbnails of a subset of allowed image types:
                        $filePath = LaramieHelpers::getLocalFilePath($item);
                        $manager = new ImageManager(['driver' => LaramieHelpers::getInterventionImageDriver()]);
                        $thumbWidths = [50]; // Currently only make one small thumbnail
                        foreach ($thumbWidths as $width) {
                            $image = $manager->make($filePath);
                            $tmpThumbnailPath = tempnam(sys_get_temp_dir(), 'LAR');
                            $image->fit($width);
                            $image->save($tmpThumbnailPath);
                            // Save to Laramie's configured storage disk:
                            $thumbnail = new File($tmpThumbnailPath);
                            Storage::disk($storageDisk)->putFileAs('', $thumbnail, LaramieHelpers::applyPathPostfix($item->path, '_'.$width), (object_get($item, 'isPublic') ? 'public' : 'private'));
                        }
                    }
                    // @optimize -- can we add a temp attribute that lets us know if we need to do this
                    // or not? We're doing it every time because it needs to be done in the case of
                    // new upload or a scaled / cropped image. Not in the case of a name being
                    // updated. But we have no way to tell right now _how_ an upload has been updated.
                    // Copy to public if specified
                    if ($item->isPublic) {
                        $filePath = LaramieHelpers::getLocalFilePath($item);

                        // `$filePath` is a pointer to a file on the local filesystem that `File` can load
                        $file = new File($filePath);
                        $tmp = Storage::disk('public')->putFileAs('', $file, $item->path, 'public');
                        $item->publicPath = Storage::disk('public')->url($tmp);
                    } else { // delete file if switched from public to private
                        try {
                            Storage::disk('public')->delete($item->path);
                        } catch (Exception $e) { /* don't error if the public version of the file can't be deleted -- may have been manually deleted */
                            dd($e->getMessage());
                        }
                    }
                } catch (Exception $e) { /* there was some issue with creating thumbs... don't bork too hard, though */
                }
                break;
            case 'LaramieAlert':
                // Only show alerts a user has received:
                if ($item->_isNew) {
                    $item->author = $user;
                    $item->status = 'Unread';
                } else {
                    $dataService = $this->getLaramieDataService();
                    $orig = $dataService->findById('LaramieAlert', $item->id);
                    $item->author = $orig->author;
                    $item->recipient = $orig->recipient;
                }
                break;
        }
    }

    /**
     * Handle post-save event.
     *
     * @param $event Laramie\Events\PostSave
     */
    public function postSave($event)
    {
        $model = $event->model;
        $item = $event->item;
        $user = $event->user;
        $type = $model->_type;
        $dataService = $this->getLaramieDataService();

        switch ($type) {
            // Create thumbnails for images
            case 'LaramieUpload':
                break;
            case '_laramieComment':
                $plainText = object_get($item, 'comment.markdown');
                preg_match_all('/@(?<mentions>[a-z0-9\-\.\_]+)/i', $plainText, $matches);
                $mentions = array_get($matches, 'mentions');
                foreach ($mentions as $mention) {
                    $tmpUser = $dataService->findByType('LaramieUser', null, function ($query) use ($mention) {
                        $query->where(DB::raw('data->>\'user\''), 'ilike', $mention.'%');
                    })->first();
                    if ($tmpUser) {
                        $alert = LaramieModel::load((object) [
                            'metaId' => object_get($item, 'metaId'),
                            'recipient' => $tmpUser,
                            'author' => $dataService->getUser(),
                            'message' => $item->comment,
                            'status' => 'Unread',
                        ]);
                        $dataService->save('LaramieAlert', $alert);
                    }
                }
        }
    }

    /**
     * Handle pre-delete.
     *
     * @param $event Laramie\Events\PostSave
     */
    public function preDelete($event)
    {
        $item = $event->item;

        if (in_array(object_get($item, 'id'), [Globals::SuperAdminRoleId, Globals::AdminRoleId])) {
            throw new Exception('You may not delete one of the core roles.');
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
