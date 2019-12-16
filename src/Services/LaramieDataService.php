<?php

namespace Laramie\Services;

use DB;
use Exception;
use Storage;
use Ramsey\Uuid\Uuid;

use Laramie\Lib\FileInfo;
use Laramie\Lib\LaramieHelpers;
use Laramie\Lib\LaramieModel;
use Laramie\Lib\ModelLoader;
use Laramie\Events\PreSave;
use Laramie\Events\PostSave;
use Laramie\Events\PreDelete;
use Laramie\Events\PostDelete;
use Laramie\Events\ShapeListQuery;
use Laramie\Events\ModifyFileInfoPreSave;
use JsonSchema\Validator;

class LaramieDataService
{
    protected $jsonConfig;
    protected $cachedItems = [];

    public function __construct()
    {
        $this->jsonConfig = ModelLoader::load();
    }

    public function getMenu()
    {
        return $this->jsonConfig->menu;
    }

    public function getModelByKey($model)
    {
        if (is_string($model)) {
            $modelToReturn = object_get($this->jsonConfig->models, $model, null);
            if ($modelToReturn == null) {
                throw new Exception(sprintf('Model type does not exist: `%s`.', $model));
            }

            return $modelToReturn;
        } elseif (object_get($model, '_type')) {
            return $model;
        }
        throw new Exception(sprintf('Model type does not exist: `%s`.', $model));
    }

    public function getAllModels()
    {
        return $this->jsonConfig->models;
    }

    public function getUser()
    {
        if (!app()->runningInConsole()) {
            return object_get(auth()->user(), '_laramie', (request()->hasSession() ? request()->session()->get('_laramie') : null));
        }

        return null;
    }

    public function getUserPrefs()
    {
        return object_get($this->getUser(), 'prefs', (object) []);
    }

    public function getUserUuid()
    {
        return object_get($this->getUser(), 'id', null);
    }

    public function saveUserPrefs($prefs)
    {
        $user = $this->getUser();
        $user->prefs = $prefs;
        if (request()->hasSession()) {
            request()->session()->put('_laramie', $user);
        }
        DB::statement('update laramie_data set data = jsonb_set(data, \'{prefs}\', \''.json_encode($prefs).'\', true) where id = ?', [$user->id]);

        return $user;
    }

    public function findTypeByTag($model, $tag)
    {
        return $this->findByType($model, ['resultsPerPage' => 0], function ($query) use ($tag) {
            $query->whereRaw(DB::raw('(select 1 from laramie_data_meta ldm where ldm.laramie_data_id = laramie_data.id and ldm.type ilike ? and data->>\'text\' ilike ? limit 1) = 1'), ['tag', $tag]);
        });
    }

    // NOTE: For now, we're only diving into aggregate relationships for single
    // item selection. Aggregate references WILL NOT be hydrated by this query.
    public function findByType($model, $options = null, $queryCallback = null, $maxPrefetchDepth = 1, $curDepth = 0, $isSpiderAggregates = false)
    {
        $model = $this->getModelByKey($model);

        $query = $this->getBaseQuery($model);
        $query = $this->augmentListQuery($query, $model, $options, $queryCallback);
        $resultsPerPage = array_get($options, 'resultsPerPage', 15);

        $factory = array_get($options, 'factory', LaramieModel::class);

        $laramieModels = $factory::load($resultsPerPage === 0 ? $query->get() : $query->paginate($resultsPerPage));

        // If we're not running in the console, whitelist qs params we need to be appended to pagination links
        if (!app()->runningInConsole() && $resultsPerPage > 0) {
            $qs = collect(request()->all())
                ->filter(function ($e, $key) {
                    return !in_array($key, ['page']);
                })
                ->all();
            $laramieModels->appends($qs);
        }

        $this->prefetchRelationships($model, $laramieModels, $maxPrefetchDepth, $curDepth);

        if ($isSpiderAggregates) {
            $laramieModels->each(function($item) use($model, $maxPrefetchDepth) {
                $this->spiderAggregates($model, $item, $maxPrefetchDepth);
            });
        }

        return $laramieModels;
    }

    public function getSingularItemId($model)
    {
        $model = $this->getModelByKey($model);

        $query = DB::table('laramie_data')
            ->where('type', $model->_type)
            ->addSelect('id')
            ->orderBy('created_at', 'asc')
            ->limit(1);
        $item = $query->first();

        if ($item && object_get($item, 'id')) {
            return $item->id;
        }

        $modelData = [
            'id' => Uuid::uuid1()->toString(),
            'user_id' => $this->getUserUuid(),
            'type' => $model->_type,
            'created_at' => 'now()',
            'updated_at' => 'now()',
        ];
        DB::table('laramie_data')->insert($modelData);

        return $modelData['id'];
    }

    public function getSingularItem($model)
    {
        return $this->findById($model, $this->getSingularItemId($model));
    }

    public function augmentListQuery($query, $model, $options = null, $queryCallback = null)
    {
        $model = $this->getModelByKey($model);

        if ($queryCallback && is_callable($queryCallback)) {
            $queryCallback($query);
        }

        $options = (object) $options;

        /*
         * Fire pre-list event: listeners MUST be synchronous. This event enables
         * the ability to dynamically change the query that retrieves items based
         * on the injected arguments.
         */
        if (config('laramie.suppress_events') !== true && object_get($options, 'shapeListQuery', true) !== false) {
            event(new ShapeListQuery($model, $query, $this->getUser(), $options));
        }

        $options = (array) $options;

        $fieldCollection = collect($model->fields);

        $computedFields = $fieldCollection
            ->filter(function ($field) {
                return $field->type == 'computed';
            })
            ->all();

        $timestampFields = $fieldCollection
            ->filter(function ($field) {
                return $field->type == 'timestamp';
            })
            ->all();

        $singularReferenceFields = $fieldCollection
            ->filter(function ($field) {
                return $field->type == 'reference' && $field->subtype == 'single';
            })
            ->all();

        $markdownFields = $fieldCollection
            ->filter(function ($field) {
                return $field->type == 'markdown';
            })
            ->all();

        $numericFields = $fieldCollection
            ->filter(function ($field) {
                return in_array($field->type, ['currency', 'number', 'range']);
            })
            ->all();

        $sort = array_get($options, 'sort') ?: $model->defaultSort;
        $sortDirection = array_get($options, 'sortDirection') ?: $model->defaultSortDirection;

        if ($sort) {
            if (in_array($sort, array_keys($computedFields))
                || in_array($sort, ['id', 'created_at', 'updated_at'])
            ) {
                // If it's a computed field or one of the table's non-json fields, sort by the field name provided
                $query->orderBy($sort, $sortDirection);
            } elseif (in_array($sort, array_keys($timestampFields))) {
                $timestampSort = $sortDirection.' nulls '.($sortDirection == 'asc' ? 'first' : 'last');
                $query->orderByRaw(DB::raw('(data #>> \'{'.$sort.',timestamp}\')::integer '.$timestampSort));
            } elseif (in_array($sort, array_keys($markdownFields))) {
                // Sort markdown fields by inner markdown
                $query->orderBy(DB::raw('(data #>> \'{"'.$sort.'","markdown"}\')'), array_get($options, 'sortDirection', 'asc'));
            } elseif (in_array($sort, array_keys($singularReferenceFields))) {
                // Sort singular reference fields by alias of relation
                $field = array_get($singularReferenceFields, $sort);
                $relatedModel = $this->getModelByKey($field->relatedModel);
                $relatedAlias = object_get($relatedModel->fields, $relatedModel->alias);
                $fieldSql = $relatedAlias->type == 'computed' ? $relatedAlias->sql : sprintf('n2.data->>\'%s\'', $relatedAlias->_fieldName);
                $query->orderBy(DB::raw('(select '.$fieldSql.' from laramie_data as n2 where (laramie_data.data->>\''.$field->_fieldName.'\')::uuid = n2.id)'), array_get($options, 'sortDirection', 'asc'));
            } elseif (in_array($sort, array_keys($numericFields))) {
                $query->orderBy(DB::raw('(data #>> \'{"'.$sort.'"}\')::float'), array_get($options, 'sortDirection', 'asc'));
            } elseif (object_get($model->fields, $sort)) {
                // Otherwise, check to see if the sort is part one of the model's dynamic fields:
                $query->orderBy(DB::raw('data #>> \'{"'.$sort.'"}\''), array_get($options, 'sortDirection', 'asc'));
            }
        }

        $filters = array_get($options, 'filters', []);
        foreach ($filters as $filter) {
            $operation = $filter->operation;
            $value = $filter->value;

            // @TODO -- document that one can specify custom sql to search a field by via adding a `sql` attribute to the filter in `ShapeListQuery`
            $field = object_get($filter, 'sql')
                ? $filter->sql
                : $this->getSearchSqlFromFieldName($model, $filter->field, $value);

            if (!$field) {
                continue; // aggregate fields will return null and may not be searched against currently
            }

            // Check to see if we need to manipulate `$value` for searching (currently limited to date fields):
            $modelField = object_get($model->fields, $filter->field);
            if (in_array($filter->field, ['_created_at', '_updated_at'])
                || in_array(object_get($modelField, 'type'), ['timestamp', 'date', 'datetime-local']))
            {
                try {
                    $value = \Carbon\Carbon::parse($value, config('laramie.timezone'))->timestamp;
                } catch (Exception $e) { $value = 0; }
            }

            switch ($operation) {
                case 'contains':
                    $query->where(DB::raw($field), 'ilike', '%'.$value.'%');
                    break;
                case 'does not contain':
                    $query->where(DB::raw($field), 'not ilike', '%'.$value.'%');
                    break;
                case 'is equal to':
                    $query->where(DB::raw($field), '=', $value);
                    break;
                case 'is not equal to':
                    $query->where(DB::raw($field), '!=', $value);
                    break;
                case 'starts with':
                    $query->where(DB::raw($field), 'ilike', $value.'%');
                    break;
                case 'does not start with':
                    $query->where(DB::raw($field), 'not ilike', $value.'%');
                    break;
                case 'is less than':
                    $query->where(DB::raw($field), '<', $value);
                    break;
                case 'is less than or equal':
                    $query->where(DB::raw($field), '<=', $value);
                    break;
                case 'is greater than':
                    $query->where(DB::raw($field), '>', $value);
                    break;
                case 'is greater than or equal':
                    $query->where(DB::raw($field), '>=', $value);
                    break;
                case 'is null':
                    $query->whereNull(DB::raw($field));
                    break;
                case 'is not null':
                    $query->whereNotNull(DB::raw($field));
                    break;
            }
        }

        $quickSearch = array_get($options, 'quickSearch');
        $quickSearchFields = object_get($model, 'quickSearch');
        if ($quickSearch && $quickSearchFields) {
            $quickSearchFields = collect($quickSearchFields)
                ->map(function ($item) use ($model) { return $this->getSearchSqlFromFieldName($model, $item); })
                ->filter();

            $query->where(function ($query) use ($quickSearchFields, $quickSearch) {
                foreach ($quickSearchFields as $field) {
                    $query->orWhere(DB::raw($field), 'ilike', '%'.$quickSearch.'%');
                }
            });
        }

        return $query;
    }

    public function getSearchSqlFromFieldName($model, $field, $value = null)
    {
        $model = $this->getModelByKey($model);

        // Specifically for quick search where no alias is provided on the model (or id is specified as a quick search field):
        if (in_array($field, ['id'])) {
            return $field.'::text';
        }

        $modelField = object_get($model->fields, $field);

        if (in_array($field, ['_created_at', '_updated_at'])) {
            // If we're searching by created_at or updated_at, the sql we're searching against to unix timestamp values. We'll be doing a similar conversion to the values being searched for.
            $field = 'date_part(\'epoch\', '.preg_replace('/^_/', '', $field).'::timestamp)::int';
        } elseif ($field == '_tags') {
            $field = '(select string_agg(ldm.data->>\'text\', \'|\') from laramie_data_meta as ldm where ldm.laramie_data_id = laramie_data.id)';
        } elseif ($field == '_comments') {
            $field = '(select string_agg(ldm.data->>\'markdown\', \'|\') from laramie_data_meta as ldm where ldm.laramie_data_id = laramie_data.id)';
        } elseif ($modelField->type == 'aggregate') {
            // Aggregate fields aren't eligible to take part in filters --
            // if a fitler is needed on an aggregate, a computed field should
            // be created to selecte the aggregate property that can then be
            // listed/searched be searched.
            return null;
        } elseif ($modelField->type == 'computed') {
            $field = $modelField->sql;
        } elseif ($modelField->type == 'timestamp') {
            $field = '(data #>> \'{'.$field.',timestamp}\')::numeric';
        } elseif (in_array($modelField->type, ['date', 'datetime-local'])) {
            $field = 'date_part(\'epoch\', (data->>\''.$field.'\')::timestamp)::int';
        } elseif ($modelField->type == 'reference') {
            // If the value we're searching by is a valid uuid (or collection of uuids), don't try to search by alias
            if (Uuid::isValid($value)
                || (collect($value)->every(function($item){ return Uuid::isValid($item); }))
            )
            {
                $field = 'data->>\''.$field.'\'';
            }
            else {
                // If we're searching a reference field by a UUID, don't do the gymnastics of searching by its alias
                $field = 'data->>\''.$field.'\'';
                $relatedModel = $this->getModelByKey($modelField->relatedModel);
                $relatedAlias = object_get($relatedModel->fields, $relatedModel->alias);

                // If the reference's alias is a computed field, modify the SQL, replacing `laramie_data` with `n2`, because we're nesting the subquery
                $fieldSql = $relatedAlias->type == 'computed' ? preg_replace('/laramie_data\./', 'n2.', $relatedAlias->sql) : sprintf('n2.data->>\'%s\'', $relatedAlias->_fieldName);

                if ($modelField->subtype == 'many') {
                    // @optimize -- this subselect takes a long time for large tables. Change to something like `data->>'field' in (select id::text from laramie_data where type='$relatedType' and data->>'$field' ilike 'keywords%')
                    $field = '(select string_agg('.$fieldSql.', \'|\') from laramie_data as n2 where n2.id::text in (select * from json_array_elements_text((laramie_data.data->>\''.$modelField->_fieldName.'\')::json)))';
                } else {
                    $field = '(select '.$fieldSql.' from laramie_data as n2 where (laramie_data.data->>\''.$modelField->_fieldName.'\')::uuid = n2.id)';
                }
            }
        } else {
            $field = 'data->>\''.$field.'\'';
        }

        return $field;
    }

    public function getUserReportsForModel($model)
    {
        $model = $this->getModelByKey($model);

        $modelKey = $model->_type;
        $userUuid = $this->getUserUuid();

        return $this->findByType($this->getModelByKey('laramieSavedReport'), ['resultsPerPage' => 0], function ($query) use ($modelKey, $userUuid) {
            $query->where(DB::raw('data->>\'relatedModel\''), $modelKey)
                ->where(function ($query) use ($userUuid) {
                    $query->where(DB::raw('data->>\'user\''), $userUuid)
                        ->orWhere(DB::raw('data->>\'isShared\''), 'true');
                });
        });
    }

    // Note that we aren't preventing cyclic relationships (on save or on fetch). We do enforce a max recursion depth, however, which will prevent infinite loops
    private function prefetchRelationships($model, $laramieModels, $maxPrefetchDepth, $curDepth)
    {
        // Set a convenience `_alias` attribute -- will be useful to save on logic where we'd otherwise be looking the alias up.
        foreach ($laramieModels as $laramieModel) {
            $laramieModel->_alias = object_get($laramieModel, $model->alias);
        }

        if ($maxPrefetchDepth < 0 || $curDepth < $maxPrefetchDepth) {
            // Get a list of all references. We're breaking the references up by type so that we can run a find by type on them (so if an alias is a computed field, we'll have access to it).
            $referencedUuids = [];
            $referenceFields = collect($model->fields)
                ->filter(function ($field) {
                    return in_array($field->type, ['reference', 'file']);
                })
                ->all();

            // First, build up a list of related laramieModels we'll need to pull down.
            foreach ($laramieModels as $laramieModel) {
                foreach ($referenceFields as $fieldKey => $field) {
                    $refs = object_get($laramieModel, $fieldKey);
                    $refs = ($refs && is_array($refs)) ? $refs : [$refs];
                    $refs = collect($refs)
                        ->filter(function ($item) {
                            return $item && Uuid::isValid($item);
                        })
                        ->all();
                    $referencedUuids[$field->relatedModel] = array_merge(array_get($referencedUuids, $field->relatedModel, []), $refs);
                }
            }

            // Next, pull those relationships
            foreach ($referencedUuids as $modelKey => $uuidList) {
                $uuidList = collect($uuidList)
                    ->filter(function($uuid) {
                        return !array_key_exists($uuid, $this->cachedItems);
                    })
                    ->unique()
                    ->toArray();

                if (count($uuidList) == 0) {
                    continue;
                }

                $relatedModels = $this->findByType(
                    $this->getModelByKey($modelKey),
                    [
                        'resultsPerPage' => 0,
                        'shapeListQuery' => false,
                    ],
                    function ($query) use ($uuidList) {
                        $query->whereIn('id', $uuidList);
                    },
                    $maxPrefetchDepth,
                    $curDepth + 1
                );
                foreach ($relatedModels as $relatedModel) {
                    $this->cachedItems[$relatedModel->id] = $relatedModel;
                }
            }

            // Finally, swap out the uuid references with concrete laramieModels.
            foreach ($laramieModels as $laramieModel) {
                foreach ($referenceFields as $fieldKey => $field) {
                    $refs = object_get($laramieModel, $fieldKey);
                    if (is_array($refs)) {
                        $newRefs = [];
                        foreach ($refs as $ref) {
                            $newRefs[] = array_get($this->cachedItems, $ref, null);
                        }
                        array_filter($newRefs);
                        $laramieModel->{$fieldKey} = $newRefs;
                    } elseif ($refs) {
                        $laramieModel->{$fieldKey} = array_get($this->cachedItems, $refs, null);
                    }
                }
            }
        }

        return $laramieModels;
    }

    public function getMetaInformation($modelName)
    {
        $model = $this->getModelByKey($modelName);

        // Create the base query
        $query = DB::table('laramie_data')
            ->where('type', $model->_type);

        $this->augmentListQuery($query, $model, ['isMetaRequest' => true, 'sort' => null]);

        $meta = (object) [];
        $meta->count = $query->count();

        $lastRecord = $query->orderBy('updated_at', 'desc')
            ->select(['updated_at', 'user_id'])
            ->first();

        if ($lastRecord) {
            $lastRecord->user = object_get(DB::table('laramie_data')
                ->where('id', $lastRecord->user_id)
                ->select([DB::raw('data->>\'user\' as user')])
                ->first(), 'user');
        }

        $meta->user = object_get($lastRecord, 'user');
        $meta->updatedAt = object_get($lastRecord, 'updated_at');

        return $meta;
    }

    public function getNumVersions($modelIds)
    {
        return DB::table('laramie_data_archive')
            ->whereIn('laramie_data_id', $modelIds)
            ->select(['laramie_data_id', DB::raw('count(*) as count')])
            ->groupBy('laramie_data_id')
            ->get();
    }

    public function getNumComments($modelIds)
    {
        return $this->getNumMeta($modelIds, 'Comment');
    }

    public function getNumTags($modelIds)
    {
        return $this->getNumMeta($modelIds, 'Tag');
    }

    private function getNumMeta($modelIds, $metaType)
    {
        return DB::table('laramie_data_meta')
            ->where('type', '=', $metaType)
            ->whereIn('laramie_data_id', $modelIds)
            ->select(['laramie_data_id', DB::raw('count(*) as count')])
            ->groupBy('laramie_data_id')
            ->get();
    }

    public function getComments($id)
    {
        return $this->getMeta($id, 'Comment')
            ->map(function ($item) { return LaramieHelpers::transformCommentForDisplay($item); });
    }

    public function updateMetaIds($oldLaramieId, $newLaramieId)
    {
        return DB::table('laramie_data_meta')
            ->where('laramie_data_id', $oldLaramieId)
            ->update(['laramie_data_id' => $newLaramieId]);
    }

    public function getTags($id)
    {
        return $this->getMeta($id, 'Tag', \DB::raw('laramie_data_meta.data->>\'text\''), 'asc');
    }

    public function getMeta($id, $metaType, $order = 'laramie_data_meta.created_at', $orderDirection = 'desc')
    {
        $rows = DB::table('laramie_data_meta')
            ->leftJoin('laramie_data', 'laramie_data_meta.user_id', '=', 'laramie_data.id')
            ->where('laramie_data_id', $id)
            ->where('laramie_data_meta.type', $metaType)
            ->select(['laramie_data_meta.*', DB::raw('laramie_data.data->>\'user\' as _user')])
            //->orderBy('laramie_data_meta.created_at', 'desc')
            ->orderBy($order, $orderDirection)
            ->get();

        return LaramieModel::load($rows);
    }

    public function getEditInfoForMetaItem($metaId)
    {
        return DB::table('laramie_data_meta')
            ->leftJoin('laramie_data', 'laramie_data_meta.laramie_data_id', '=', 'laramie_data.id')
            ->where('laramie_data_meta.id', $metaId)
            ->select(['laramie_data.id', 'laramie_data.type'])
            ->first();
    }

    public function deleteMeta($id)
    {
        if (Uuid::isValid($id)) {
            $item = DB::table('laramie_data_meta')
                ->where('id', $id)
                ->first();
            DB::table('laramie_data_meta')
                ->where('id', $id)
                ->delete();

            return $item;
        }

        return false;
    }

    public function createTag($modelId, $tag)
    {
        $success = true;
        $tags = array_filter(preg_split('/\s*,\s*/', $tag));
        foreach ($tags as $tag) {
            $success = $success && $this->createMeta($modelId, 'Tag', (object) ['text' => $tag]);
        }

        return $success;
    }

    public function createComment($modelId, $comment)
    {
        $comment = $this->createMeta($modelId, 'Comment', $comment);
        if ($comment) {
            // @note: the `_laramieComment` model does not exist, it is simply being used pass info on to a listener
            event(new PostSave((object) ['model' => 'LaramieMeta', '_type' => '_laramieComment'], LaramieModel::load((object) ['metaId' => $comment['id'], 'comment' => json_decode($comment['data'])]), $this->getUser()));
        }
    }

    private function createMeta($modelId, $type, $data)
    {
        if (Uuid::isValid($modelId)) {
            $modelData = [
                'id' => Uuid::uuid1()->toString(),
                'user_id' => $this->getUserUuid(),
                'laramie_data_id' => $modelId,
                'type' => $type,
                'data' => json_encode($data),
                'created_at' => 'now()',
                'updated_at' => 'now()',
            ];
            DB::table('laramie_data_meta')->insert($modelData);

            return $modelData;
        }

        return false;
    }

    public function findById($model, $id, $maxPrefetchDepth = 5)
    {
        if (array_key_exists($id, $this->cachedItems)) {
            return $this->cachedItems[$id];
        }

        $model = $this->getModelByKey($model);

        if ($id == 'new') {
            return new LaramieModel();
        }

        if ($id === null) {
            return null;
        }

        if (!Uuid::isValid($id)) {
            throw new Exception('Id must be a valid UUID ' . $id);
        }

        $query = $this->getBaseQuery($model)
            ->where('id', $id);

        $dbItem = $query->first();

        if ($dbItem === null) {
            return null;
        }

        $item = array_first($this->prefetchRelationships($model, [LaramieModel::load($dbItem)], $maxPrefetchDepth, 0));

        // NOTE: we're only diving into aggregate relationships for single item
        // selection. What this means is that reference fields within deeply
        // nested aggregates won't be returned by `findByType`.
        $this->spiderAggregates($model, $item, $maxPrefetchDepth);

        $this->cachedItems[$item->id] = $item;

        return $item;
    }

    private function spiderAggregates($model, $item, $maxPrefetchDepth)
    {
        $aggregateFields = collect($model->fields)
            ->filter(function ($e) {
                return $e->type == 'aggregate';
            })
            ->all();

        // Recursively dive into aggregate fields defined on the model (e.g.,
        // aggregate1 -> aggregate2 -> aggregate3 -> ...) `spiderAggregatesHelper`
        // is slightly different. It finds reference fields within aggregates and
        // hydrates them (which in turn is a recursive process).
        foreach ($aggregateFields as $aggregateKey => $aggregateField) {
            $aggregateData = object_get($item, $aggregateKey);
            $aggregateData = ($aggregateData && is_array($aggregateData)) ? $aggregateData : [$aggregateData];

            foreach ($aggregateData as $data) {
                $this->spiderAggregates($aggregateField, $data, $maxPrefetchDepth);
            }
        }

        try {
            $this->spiderAggregatesHelper($aggregateFields, $item, $maxPrefetchDepth);
        } catch (Exception $e) { /* Might have gotten here because the schema of the model changed between edits. */
        }
    }

    private function spiderAggregatesHelper($aggregateFields, $item, $maxPrefetchDepth)
    {
        if ($maxPrefetchDepth <= 0) {
            return;
        }

        $prefetchDepth = max(0, $maxPrefetchDepth - 1);

        foreach ($aggregateFields as $aggregateKey => $aggregateField) {
            $aggregateData = object_get($item, $aggregateKey, null);
            if ($aggregateData !== null) {
                $aggregateReferenceFields = collect($aggregateField->fields)
                    ->filter(function ($e) {
                        return in_array($e->type, ['reference', 'file']);
                    })
                    ->all();
                foreach ($aggregateReferenceFields as $aggregateReferenceFieldKey => $aggregateReferenceField) {
                    // If `$aggregateData` is an array, we're processing a repeatable aggregate.
                    if (is_array($aggregateData)) {
                        for ($i = 0; $i < count($aggregateData); ++$i) {
                            $aggregateReferenceFieldData = object_get($aggregateData[$i], $aggregateReferenceFieldKey);
                            if (is_array($aggregateReferenceFieldData)) {
                                // If `$aggregateReferenceFieldData` is an array, we're processing a `reference-many` field
                                for ($j = 0; $j < count($aggregateReferenceFieldData); ++$j) {
                                    $aggregateReferenceFieldData[$j] = $this->findById($this->getModelByKey($aggregateReferenceField->relatedModel), $aggregateReferenceFieldData[$j], $prefetchDepth);
                                }
                            } else {
                                $aggregateReferenceFieldData = $this->findById($this->getModelByKey($aggregateReferenceField->relatedModel), $aggregateReferenceFieldData, $prefetchDepth);
                            }
                            $aggregateData[$i]->{$aggregateReferenceFieldKey} = $aggregateReferenceFieldData;
                        }
                    } else {
                        // Otherwise, it's not repeatable.
                        $aggregateReferenceFieldData = object_get($aggregateData, $aggregateReferenceFieldKey);
                        if (is_array($aggregateReferenceFieldData)) {
                            // If `$aggregateReferenceFieldData` is an array, we're processing a `reference-many` field
                            for ($i = 0; $i < count($aggregateReferenceFieldData); ++$i) {
                                $aggregateReferenceFieldData[$i] = $this->findById($this->getModelByKey($aggregateReferenceField->relatedModel), $aggregateReferenceFieldData[$i], $prefetchDepth);
                            }
                        } else {
                            $aggregateReferenceFieldData = $this->findById($this->getModelByKey($aggregateReferenceField->relatedModel), $aggregateReferenceFieldData, $prefetchDepth);
                        }
                        $aggregateData->{$aggregateReferenceFieldKey} = $aggregateReferenceFieldData;
                    }
                }
            }
            $item->{$aggregateKey} = $aggregateData;
        }
    }

    public function findByIdSuperficial($model, $id)
    {
        $model = $this->getModelByKey($model);

        if ($id == 'new') {
            return new LaramieModel();
        }

        $query = $this->getBaseQuery($model)
            ->where('id', $id);

        return array_first([LaramieModel::load($query->first())]);
    }

    public function findItemRevisions($id)
    {
        if (Uuid::isValid($id)) {
            return DB::table('laramie_data_archive as a')
                ->leftJoin('laramie_data as ld', 'a.user_id', '=', 'ld.id')
                ->where('laramie_data_id', $id)
                ->select(['a.id', 'a.updated_at', DB::raw('ld.data#>>\'{user}\' as user')])
                ->orderBy('a.created_at', 'desc')
                ->get();
        }

        return [];
    }

    public function findPreviousItem($itemId, $olderThanRevisionId)
    {
        if (Uuid::isValid($itemId)) {
            $query = DB::table('laramie_data_archive')
                ->where('laramie_data_id', '=', $itemId)
                ->orderBy('created_at', 'desc');

            if ($olderThanRevisionId) {
                $query->whereRaw(DB::raw('created_at < (select created_at from laramie_data_archive lda where lda.id = ?)'), [$olderThanRevisionId]);
            }

            return LaramieModel::load($query->first());
        }
        throw new Exception('Invalid item id');
    }

    public function getItemRevision($id)
    {
        if (Uuid::isValid($id)) {
            return LaramieModel::load(DB::table('laramie_data_archive')
                ->where('id', $id)
                ->first());
        }
        throw new Exception('Invalid item id');
    }

    public function restoreRevision($id)
    {
        $archivedItem = $this->getItemRevision($id);
        if (!$archivedItem->id) {
            throw new Exception('Could not find archived item.');
        }

        // Check to see if we need to archive the primary item (we could be trying to restore the same thing just restored)
        $shouldArchive = array_get(DB::select('select count(*) as count from laramie_data where id = ? and data != (select data from laramie_data_archive lda where laramie_data_id = laramie_data.id order by created_at desc limit 1)', [$archivedItem->laramie_data_id]), 0)->count > 0;
        if ($shouldArchive) {
            // Archive the primary item
            DB::statement('insert into laramie_data_archive (id, user_id, laramie_data_id, type, data, created_at, updated_at) select ?, user_id, id, type, data, now(), updated_at from laramie_data where id = ?', [Uuid::uuid1()->toString(), $archivedItem->laramie_data_id]);
        }
        // Update the primary item with the data from the revision we're restoring
        DB::statement('update laramie_data set updated_at = now(), user_id = (select user_id from laramie_data_archive where id = ?), data = (select data from laramie_data_archive where id = ?) where id = ?', [$id, $id, $archivedItem->laramie_data_id]);

        // Return the _archived_ item -- we'll use it in an alert message
        return $archivedItem;
    }

    public function deleteRevision($id)
    {
        if (Uuid::isValid($id)) {
            DB::table('laramie_data_archive')
                ->where('id', $id)
                ->delete();
        }
    }

    public function getBaseQuery($model)
    {
        $model = $this->getModelByKey($model);

        // Create the base query
        $query = DB::table('laramie_data')
            ->where('type', $model->_type)
            ->addSelect('*');

        $computedFields = collect($model->fields)
            ->filter(function ($field) {
                return $field->type == 'computed';
            })
            ->each(function ($field, $key) use ($query) {
                $query->addSelect(DB::raw($field->sql.' as "'.$key.'"'));
            });

        return $query;
    }

    private function flattenRelationships($fieldHolder, $data)
    {
        foreach ($fieldHolder->fields as $key => $field) {
            if ($field->type == 'reference') {
                if ($field->subtype == 'single') {
                    $data->{$key} = is_string(data_get($data, $key)) && Uuid::isValid($data->{$key})
                        ? $data->{$key}
                        : data_get($data, $key.'.id');
                } else {
                    $data->{$key} = collect(data_get($data, $key))
                        ->map(function ($e) {
                            return is_string($e) && Uuid::isValid($e)
                                ? $e
                                : data_get($e, 'id');
                        })
                        ->filter()
                        ->values()
                        ->all();
                }
            } elseif ($field->type == 'file') {
                $data->{$key} = data_get($data, $key.'.uploadKey');
            } elseif ($field->type == 'aggregate') {
                $aggregateData = data_get($data, $key, null);
                if (is_array($aggregateData)) {
                    for ($i = 0; $i < count($aggregateData); ++$i) {
                        $aggregateData[$i] = $this->flattenRelationships($field, $aggregateData[$i]);
                    }
                } else {
                    $aggregateData = $this->flattenRelationships($field, $aggregateData);
                }
                $data->{$key} = $aggregateData;
            }
        }

        return $data;
    }

    public function save($model, LaramieModel $laramieModel, $validate = true, $maxPrefetchDepth = 5)
    {
        $model = $this->getModelByKey($model);
        // Save a record of the original id. After saving, we'll reset the item's id back to the original so we have
        // context as to if the item is new in the PostSave event.
        $origId = object_get($laramieModel, '_origId');

        /*
         * Fire pre-save event: listeners MUST be synchronous. This event
         * enables the ability to dynamically alter the model that will be
         * saved based on the injected arguments.
         */
        if (config('laramie.suppress_events') !== true) {
            event(new PreSave($model, $laramieModel, $this->getUser()));
        }

        $data = clone $laramieModel;
        $data->id = $data->id ?: data_get($data, '_metaId', Uuid::uuid1()->toString());
        $data->updated_at = \Carbon\Carbon::now(config('laramie.timezone'))->toDateTimeString();
        if (!object_get($data, '_origId')) {
            // Insert
            $data->type = $model->_type;
            $data->created_at = object_get($data, 'created_at', \Carbon\Carbon::now(config('laramie.timezone'))->toDateTimeString());
        } else if (array_key_exists($data->id, $this->cachedItems)) {
            unset($this->cachedItems[$data->id]);
        }

        // Relation fields are transformed into the id(s) of the items they
        // represent before being persisted in the db.
        $data = $this->flattenRelationships($model, $data);

        // Remove fields that aren't part of the the schema (old attributes
        // that may have been removed will still exist in archived versions of the
        // data). Basically what this means is that what gets saved in the db must
        // comply with the schema.
        $allowedFields = ['id', 'type', 'created_at', 'updated_at'];
        foreach ($model->fields as $key => $field) {
            if (!in_array($field->type, ['computed', 'html'])) {
                // Don't save computed/html fields (these will be calculated every time the item is accessed).
                $allowedFields[] = $key;
            }
        }

        // Flip the array so we can do O(1) lookups rather than O(N) ones
        $allowedFields = array_flip($allowedFields);
        foreach ($data as $key => $field) {
            if (!preg_match('/^_/', $key) && !array_key_exists($key, $allowedFields)) {
                unset($data->{$key});
            }
        }

        if ($validate) {
            // Perform JSON schema validation on the model. The real benefit of
            // this is when users are creating data outside of the admin (and saving
            // through this method). Validation is still happening to ensure that
            // what's getting saved adheres to a particular schema.
            $errors = [];
            $validator = new Validator();
            $validator->check($data, object_get($model, '_jsonValidator', ModelLoader::getValidationSchema($model)));
            if (!$validator->isValid()) {
                foreach ($validator->getErrors() as $error) {
                    $errors[] = sprintf('%s: %s', $error['property'], $error['message']);
                }
            }

            if ($errors) {
                throw new Exception(implode('<br>', $errors));
            }
        }

        $modelData = $data->toArray();
        $modelData['user_id'] = $this->getUserUuid();

        if (object_get($data, '_origId')) {
            // Update
            $archiveId = Uuid::uuid1()->toString();
            DB::statement('insert into laramie_data_archive (id, user_id, laramie_data_id, type, data, created_at, updated_at) select ?, user_id, id, type, data, now(), updated_at from laramie_data where id = ?', [$archiveId, $data->id]);
            DB::table('laramie_data')->where('id', $data->id)->update($modelData);
            // Delete the newly inserted archived version if it exactly matches
            // the updated version. We can potentially mitigate this step by
            // leveraging the _origData attribute on $data before inserting
            // the archive version in the first place, but whitespace doesn't
            // match up between $data->_origData and $modelData['data']...
            DB::statement('delete from laramie_data_archive where id = ? and data = (select data from laramie_data where id = ?)', [$archiveId, $data->id]);
        } else {
            // Insert
            $modelData['type'] = $model->_type;
            DB::table('laramie_data')->insert($modelData);
        }

        // Refresh the data from the db (because computed fields may have changed, etc):
        $item = $this->findById($model, $data->id, $maxPrefetchDepth);

        /*
         * Fire post-save event: listeners MAY be asynchronous. This event
         * enables the ability to perform actions _after_ an item is saved,
         * such as deliver email or implement a custom workflow for a model. To
         * aid serialization, we're only injecting the string of the model type
         * and the id of the item saved.
         *
         * Note that because we're selecting the item from the db, we need to
         * pass additional context with the item to help listeners determine if
         * it was a new item or not:
         */

        $item->_wasNew = !object_get($data, '_origId');

        if (config('laramie.suppress_events') !== true) {
            event(new PostSave($model, $item, $this->getUser()));
        }

        return $item;
    }

    // `$isDeleteHistory` specifies whether or not to remove the item's history as well. If set to false, we'll create a snapshot of it in the archive table before deletion.
    public function deleteById($model, $id, $isDeleteHistory = false)
    {
        $model = $this->getModelByKey($model);
        $item = $this->findByIdSuperficial($model, $id);

        if (!Uuid::isValid($id)) {
            return false;
        }

        \DB::beginTransaction();

        try {
            if (config('laramie.suppress_events') !== true) {
                event(new PreDelete($model, $item, $this->getUser()));
            }

            if ($isDeleteHistory) {
                DB::table('laramie_data_archive')
                    ->where('laramie_data_id', $id)
                    ->delete();
            } else {
                DB::statement('insert into laramie_data_archive (id, user_id, laramie_data_id, type, data, created_at, updated_at) select ?, user_id, id, type, data, now(), updated_at from laramie_data where id = ?', [Uuid::uuid1()->toString(), $id]);
            }

            DB::table('laramie_data')
                ->where('id', $id)
                ->delete();

            if (config('laramie.suppress_events') !== true) {
                event(new PostDelete($model, $item, $this->getUser()));
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        return true;
    }

    public function cloneById($id)
    {
        $newId = Uuid::uuid1()->toString();
        DB::statement('insert into laramie_data (id, user_id, type, data, created_at, updated_at) select ?, ?, type, data, now(), now() from laramie_data where id = ?', [$newId, $this->getUser()->id, $id]);

        return $newId;
    }

    public function saveFile($file, $isPublic, $source = null, $destination = null)
    {
        $storageDisk = config('laramie.storage_disk');
        $storageDriver = config('filesystems.disks.'.$storageDisk.'.driver');
        $user = $this->getUser();

        // There's an issue with certain file types not getting an extension
        // set with `$file->store` (namely svg). Instead of relying on
        // Laravel/Symfony to auto-generate a file name, create one ourselves:
        $destination = $destination ?: sprintf('%s/%s._fix_extension',
            data_get($user, 'id', \Laramie\Globals::DummyId),
            preg_replace('/[-]/', '', Uuid::uuid4()->toString()));

        // `$file` above can be an UploadedFile or a simple Illuminate file. Abstract the name/meta gathering to `FileInfo` -- will also allow a hook for events to modify before saving.
        $fileInfo = new FileInfo($file, $isPublic, $source, $destination);

        if (config('laramie.suppress_events') !== true) {
            event(new ModifyFileInfoPreSave($this->getUser(), $fileInfo));
        }

        $id = Uuid::uuid1()->toString();
        $laramieUpload = new LaramieModel();
        $laramieUpload->id = $id;
        $laramieUpload->uploadKey = $id;
        $laramieUpload->name = $fileInfo->name;
        $laramieUpload->extension = $fileInfo->extension;
        $laramieUpload->mimeType = $fileInfo->mimeType;
        $laramieUpload->path = Storage::disk($storageDisk)->putFileAs('laramie', $file, $fileInfo->destination, ['visibility' => 'private']); // this is our master copy, it should always be private (with the exception of its admin-generated thumbs; we'll make those public if the file is public).
        $laramieUpload->isPublic = $fileInfo->isPublic;
        $laramieUpload->source = $fileInfo->source;

        $laramieUpload->fullPath = Storage::disk($storageDisk)->url($laramieUpload->path);
        if ($storageDriver == 'local') {
            $laramieUpload->fullPath = Storage::disk($storageDisk)->getDriver()->getAdapter()->applyPathPrefix($laramieUpload->path);
        }

        $model = $this->getModelByKey('laramieUpload');

        // Save and get the Laramie model
        $laramieUpload = $this->save($model, $laramieUpload);

        return $laramieUpload;
    }

    public function getFileInfo($id)
    {
        if (Uuid::isValid($id)) {
            $laramieUpload = $this->getBaseQuery($this->getModelByKey('laramieUpload'))
                ->where('id', $id)
                ->first();

            return object_get($laramieUpload, 'id') ? json_decode($laramieUpload->data) : null;
        }

        return null;
    }

    public function removeFile($fileInfo)
    {
        // If there is no file to remove, return
        if (!$fileInfo || !object_get($fileInfo, 'uploadKey')) {
            return;
        }

        $storageDisk = config('laramie.storage_disk');
        try {
            Storage::disk($storageDisk)->delete($fileInfo->path);
        } catch (Exception $e) {
            // swallow errors for now -- do we really want to cause a stir if the file can't be deleted from disk?
        }

        $this->deleteById($fileInfo->uploadKey);
    }

    public function getBulkActionQuery($model, $postData)
    {
        $model = $this->getModelByKey($model);

        // $postData is not passed by reference, so making changes here won't affect its value in the caller
        $postData['sort'] = null;
        $postData['sort-direction'] = null;

        return DB::table('laramie_data')
            ->whereIn('id', function ($query) use ($model, $postData) {
                $query->select(['id'])
                    ->from('laramie_data')
                    ->where('type', $model->_type);

                $this->augmentListQuery($query, $model, $postData);

                $isAllSelected = array_get($postData, 'bulk-action-all-selected') === '1';

                if (!$isAllSelected) {
                    $itemIds = collect(array_get($postData, 'bulk-action-ids', []))
                        ->filter(function ($item) {
                            return $item && Uuid::isValid($item);
                        });
                    $query->whereIn(DB::raw('id::text'), $itemIds);
                }
            });
    }
}
