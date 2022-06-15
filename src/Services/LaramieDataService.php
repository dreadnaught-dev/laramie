<?php

namespace Laramie\Services;

use Arr;
use Carbon\Carbon;
use DB;
use Exception;
use JsonSchema\Validator;
use Ramsey\Uuid\Uuid;
use Storage;

use Laramie\Lib\FieldContainer;
use Laramie\Lib\FieldSpec;
use Laramie\Lib\FileInfo;
use Laramie\Lib\LaramieHelpers;
use Laramie\Lib\LaramieModel;
use Laramie\Lib\ModelLoader;
use Laramie\Lib\ModelSpec;
use Laramie\Hook;
use Laramie\Hooks\FilterQuery;
use Laramie\Hooks\ModifyFileInfoPreSave;
use Laramie\Hooks\PostDelete;
use Laramie\Hooks\PostFetch;
use Laramie\Hooks\PostSave;
use Laramie\Hooks\PreDelete;
use Laramie\Hooks\PreSave;

class LaramieDataService
{
    protected $jsonConfig;
    protected $cachedItems = [];
    protected static $cachedUser = null;

    public function __construct()
    {
        $this->jsonConfig = ModelLoader::load();
    }

    public function getMenu()
    {
        return $this->jsonConfig->menu;
    }

    public function getModelByKey(mixed $model)
    {
        if (is_string($model)) {
            $modelConfig = data_get($this->jsonConfig->models, $model, null);
            if ($modelConfig == null) {
                throw new Exception(sprintf('Model type does not exist: `%s`.', $model));
            }

            return new ModelSpec(json_decode(json_encode($modelConfig)));
        } elseif ($model instanceof ModelSpec) {
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
        if (!self::$cachedUser) {
            self::$cachedUser = auth()->user();
        }

        return self::$cachedUser;
    }

    public function getUserId()
    {
        return data_get($this->getUser(), 'id', null);
    }

    public function findTypeByTag($model, $tag)
    {
        return $this->findByType($model, ['resultsPerPage' => 0, 'source' => 'admin'], function ($query) use ($tag) {
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
        $resultsPerPage = data_get($options, 'resultsPerPage', config('laramie.results_per_page', 20));

        $factory = data_get($options, 'factory', $model->getFactory());

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
                $this->spiderAggregates($model, $item, $maxPrefetchDepth); // @TODO preston -- create common interface for aggregate fields and ModelSpec (FieldContainer)?
            });
        }

        $options['curDepth'] = $curDepth;
        if (config('laramie.suppress_events') !== true) {
            Hook::fire(new PostFetch($model, $laramieModels, $this->getUser(), $options));
        }

        return $laramieModels;
    }

    public function getNumResultsMatchingFilters($model, $options = null, $queryCallback = null)
    {
        $model = $this->getModelByKey($model);

        $query = $this->getBaseQuery($model);
        $query = $this->augmentListQuery($query, $model, $options, $queryCallback);

        return $query->count();
    }

    public function getSingularItemId($model)
    {
        $model = $this->getModelByKey($model);

        $query = DB::table('laramie_data')
            ->where('type', $model->getType())
            ->addSelect('id')
            ->orderBy('created_at', 'asc')
            ->limit(1);
        $item = $query->first();

        if ($item && data_get($item, 'id')) {
            return $item->id;
        }

        $modelData = [
            'id' => Uuid::uuid1()->toString(),
            'user_id' => $this->getUserId(),
            'type' => $model->getType(),
            'created_at' => $this->getDbNow(),
            'updated_at' => $this->getDbNow(),
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

        $options = (array) $options;

        $fieldCollection = collect($model->getFieldsSpecs());

        $computedFields = $fieldCollection
            ->filter(function ($field) {
                return $field->getType() == 'computed';
            })
            ->all();

        $timestampFields = $fieldCollection
            ->filter(function ($field) {
                return $field->getType() == 'timestamp';
            })
            ->all();

        $singularReferenceFields = $fieldCollection
            ->filter(function ($field) {
                return $field->getType() == 'reference' && $field->getSubtype() == 'single';
            })
            ->all();

        $markdownFields = $fieldCollection
            ->filter(function ($field) {
                return $field->getType() == 'markdown';
            })
            ->all();

        $numericFields = $fieldCollection
            ->filter(function ($field) {
                return in_array($field->getType(), ['currency', 'number', 'range']);
            })
            ->all();

        $sort = data_get($options, 'sort') ?: $model->getDefaultSort();
        $sortDirection = data_get($options, 'sortDirection') ?: $model->getDefaultSortDirection();

        if ($sort) {
            $field = data_get($fieldCollection, $sort);
            if (in_array($sort, array_keys($computedFields))
                || in_array($sort, ['id', 'created_at', 'updated_at'])
            ) {
                // If it's a computed field or one of the table's non-json fields, sort by the field name provided
                $sort = optional($field)->isDeferred()
                    ? DB::raw($field->getSql())
                    : $sort;

                $query->orderBy($sort, $sortDirection);
            } elseif (in_array($sort, array_keys($timestampFields))) {
                $timestampSort = $sortDirection.' nulls '.($sortDirection == 'asc' ? 'first' : 'last');
                $query->orderByRaw(DB::raw('(data #>> \'{'.$sort.',timestamp}\')::integer '.$timestampSort));
            } elseif (in_array($sort, array_keys($markdownFields))) {
                // Sort markdown fields by inner markdown
                $query->orderBy(DB::raw('(data #>> \'{"'.$sort.'","markdown"}\')'), data_get($options, 'sortDirection', 'asc'));
            } elseif (in_array($sort, array_keys($singularReferenceFields))) {
                // Sort singular reference fields by alias of relation
                $field = data_get($singularReferenceFields, $sort);
                $relatedModel = $this->getModelByKey($field->relatedModel);
                $relatedAlias = $relatedModel->getFieldSpec($relatedModel->getAlias());
                $fieldSql = $relatedAlias->getType() == 'computed' ? $relatedAlias->getSql() : sprintf('n2.data->>\'%s\'', $relatedAlias->getFieldName());
                $query->orderBy(DB::raw('(select '.$fieldSql.' from laramie_data as n2 where (laramie_data.data->>\''.$field->_fieldName.'\')::uuid = n2.id)'), data_get($options, 'sortDirection', 'asc'));
            } elseif (in_array($sort, array_keys($numericFields))) {
                $query->orderBy(DB::raw('(data #>> \'{"'.$sort.'"}\')::float'), data_get($options, 'sortDirection', 'asc'));
            } elseif ($model->getFieldSpec($sort)) {
                // Otherwise, check to see if the sort is part one of the model's dynamic fields:
                $query->orderBy(DB::raw('data #>> \'{"'.$sort.'"}\''), data_get($options, 'sortDirection', 'asc'));
            }
        }

        $filterGroups = collect(data_get($options, 'filters', []))
            ->groupBy(function($item) { return data_get($item, 'field'); });

        // @TODO -- add laramie config param to dictate how to handle group filter logic (AND or OR). For now, default to ORing within a group (as defined by which field is being searched).
        // For example, if multiple user filters are added, we will return records that match any user supplied.

        foreach ($filterGroups as $filters) {
            $query->where(function($query) use($filters, $model) {
                foreach ($filters as $filter) {
                    $operation = $filter->operation;
                    $value = $filter->value;

                    // @TODO -- document that one can specify custom sql to search a field by via adding a `sql` attribute to the filter in `FilterQuery`
                    $field = data_get($filter, 'sql')
                        ? $filter->sql
                        : $this->getSearchSqlFromFieldName($model, $filter->field, $value);

                    if (!$field) {
                        continue; // aggregate fields will return null and may not be searched against currently
                    }

                    // Check to see if we need to manipulate `$value` for searching (currently limited to date fields):
                    $modelField = $model->getFieldSpec($filter->field);
                    if ($operation !== 'between-dates' && (in_array($filter->field, ['_created_at', '_updated_at'])
                        || in_array($modelField->getDataType(), ['dbtimestamp', 'timestamp', 'date', 'datetime-local'])))
                    {
                        try {
                            $value = Carbon::parse($value)->timestamp;
                        } catch (Exception $e) { $value = 0; }
                    }

                    switch ($operation) {
                        case 'contains':
                            $query->orWhere(DB::raw($field), 'ilike', '%'.$value.'%');
                            break;
                        case 'does not contain':
                            $query->orWhere(DB::raw($field), 'not ilike', '%'.$value.'%');
                            break;
                        case 'is equal to':
                            $query->orWhere(DB::raw($field), '=', $value);
                            break;
                        case 'is not equal to':
                            $query->orWhere(DB::raw($field), '!=', $value);
                            break;
                        case 'starts with':
                            $query->orWhere(DB::raw($field), 'ilike', $value.'%');
                            break;
                        case 'does not start with':
                            $query->orWhere(DB::raw($field), 'not ilike', $value.'%');
                            break;
                        case 'is less than':
                            $query->orWhere(DB::raw($field), '<', $value);
                            break;
                        case 'is less than or equal':
                            $query->orWhere(DB::raw($field), '<=', $value);
                            break;
                        case 'is greater than':
                            $query->orWhere(DB::raw($field), '>', $value);
                            break;
                        case 'is greater than or equal':
                            $query->orWhere(DB::raw($field), '>=', $value);
                            break;
                        case 'is null':
                            $query->orWhereNull(DB::raw($field));
                            break;
                        case 'is not null':
                            $query->orWhereNotNull(DB::raw($field));
                            break;
                        case 'between-dates':
                            $dates = collect(preg_split('/[|]/', $value))
                                ->map(function($item) {
                                    try {
                                        return Carbon::parse($item);
                                    } catch (\Exception $e) { /* error parsing date. don't add filter */ }
                                })
                                ->filter()
                                ->toArray();

                            if (count($dates) === 2) {
                                $query->orWhere(function($query) use($field, $dates) {
                                    $query->where(DB::raw($field), '>=', $dates[0]->startOfDay()->timestamp);
                                    $query->where(DB::raw($field), '<=', $dates[1]->endOfDay()->timestamp);
                                });
                            }
                            break;
                    }
                }
            });
        }

        $quickSearch = data_get($options, 'quickSearch');
        $quickSearchFields = $model->getQuickSearch();
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

        /*
         * Fire filter-query event: listeners MUST be synchronous. This event enables
         * the ability to dynamically change the query that retrieves items based
         * on the injected arguments.
         */
        if (config('laramie.suppress_events') !== true && data_get($options, 'filterQuery', true) !== false) {
            Hook::fire(new FilterQuery($model, $query, $this->getUser(), $options));
        }

        return $query;
    }

    public function getSearchSqlFromFieldName($model, string $field, $value = null)
    {
        $model = $this->getModelByKey($model);

        // Specifically for quick search where no alias is provided on the model (or id is specified as a quick search field):
        if (in_array($field, ['id'])) {
            return $field.'::text';
        }

        if (array_key_exists($field, config('laramie.laramie_data_fields'))) {
            return $field;
        }

        $modelField = $model->getFieldSpec($field);

        $isComputedField = $modelField->getType() === 'computed';
        $modelFieldType = $modelField->getDataType();

        // If searching by the `data` field, don't transform -- it's a manual query
        if (preg_match('/\bdata\b/', $field)) {
            return 'data';
        }
        if ($modelFieldType == 'dbtimestamp') {
            if ($isComputedField) {
                $field = 'date_part(\'epoch\', '.preg_replace('/^_/', '', $modelField->getSql()).'::timestamp)::int';
            } else {
                $field = 'date_part(\'epoch\', '.preg_replace('/^_/', '', $field).'::timestamp)::int';
            }
        } elseif ($field == '_tags') {
            $field = '(select string_agg(ld2.data->>\'tag\', \'|\') from laramie_data as ld2 where (ld2.data->>\'relatedItemId\')::uuid = laramie_data.id)';
        } elseif ($field == '_comments') {
            $field = '(select string_agg(ld2.data->>\'markdown\', \'|\') from laramie_data as ld2 where (ld2.data->>\'relatedItemId\'):uuid = laramie_data.id)';
        } elseif ($modelFieldType == 'aggregate') {
            // Aggregate fields aren't eligible to take part in filters --
            // if a fitler is needed on an aggregate, a computed field should
            // be created to select the aggregate property that can then be
            // listed/searched be searched.
            return null;
        } elseif ($modelFieldType == 'computed') {
            $field = $modelField->getSql();
        } elseif ($modelFieldType == 'boolean') {
            $field = '(data->>\''.$field.'\')::boolean';
        } elseif ($modelFieldType == 'timestamp') {
            $field = '(data #>> \'{'.$field.',timestamp}\')::numeric';
        } elseif (in_array($modelFieldType, ['date', 'datetime-local'])) {
            $field = 'date_part(\'epoch\', (data->>\''.$field.'\')::timestamp)::int';
        } elseif ($modelFieldType == 'reference') {
            // If the value we're searching by is a valid uuid (or collection of uuids), don't try to search by alias
            if (LaramieHelpers::isValidUuid($value)
                || (collect($value)->every(function($item){ return LaramieHelpers::isValidUuid($item); }))
            )
            {
                $field = 'data->>\''.$field.'\'';
            }
            else {
                // If we're searching a reference field by a UUID, don't do the gymnastics of searching by its alias
                $field = 'data->>\''.$field.'\'';
                $relatedModel = $this->getModelByKey($modelField->getRelatedModel());
                $relatedAlias = $relatedModel->getFieldSpec($relatedModel->getAlias());

                // If the reference's alias is a computed field, modify the SQL, replacing `laramie_data` with `n2`, because we're nesting the subquery
                $fieldSql = $relatedAlias->getType() == 'computed' ? preg_replace('/laramie_data\./', 'n2.', $relatedAlias->getSql()) : sprintf('n2.data->>\'%s\'', $relatedAlias->getFieldName());

                if ($modelField->getSubtype() == 'many') {
                    // @optimize -- this subselect takes a long time for large tables. Change to something like `data->>'field' in (select id::text from laramie_data where type='$relatedType' and data->>'$field' ilike 'keywords%')
                    $field = '(select string_agg('.$fieldSql.', \'|\') from laramie_data as n2 where n2.id::text in (select * from json_array_elements_text((laramie_data.data->>\''.$modelField->getFieldName().'\')::json)))';
                } else {
                    $field = '(select '.$fieldSql.' from laramie_data as n2 where (laramie_data.data->>\''.$modelField->getFieldName().'\')::uuid = n2.id)';
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

        $modelKey = $model->getType();
        $userUuid = $this->getUserId();

        return $this->findByType($this->getModelByKey('laramieSavedReport'), ['source' => 'admin', 'resultsPerPage' => 0], function ($query) use ($modelKey, $userUuid) {
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
            $laramieModel->_alias = data_get($laramieModel, $model->getAlias());
        }

        if ($maxPrefetchDepth < 0 || $curDepth < $maxPrefetchDepth) {
            // Get a list of all references. We're breaking the references up by type so that we can run a find by type on them (so if an alias is a computed field, we'll have access to it).
            $referencedUuids = [];
            $referenceFields = collect($model->getFieldsSpecs())
                ->filter(function ($field) {
                    return in_array($field->getType(), ['reference', 'file']);
                })
                ->all();

            // First, build up a list of related laramieModels we'll need to pull down.
            foreach ($laramieModels as $laramieModel) {
                foreach ($referenceFields as $fieldKey => $field) {
                    $refs = data_get($laramieModel, $fieldKey);
                    $refs = ($refs && is_array($refs)) ? $refs : [$refs];
                    $refs = collect($refs)
                        ->filter(function ($item) {
                            return $item && LaramieHelpers::isValidUuid($item);
                        })
                        ->all();
                    $referencedUuids[$field->getRelatedModel()] = array_merge(data_get($referencedUuids, $field->getRelatedModel(), []), $refs);
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

                if (count($uuidList) === 0) {
                    continue;
                }

                $relatedModels = $this->findByType(
                    $this->getModelByKey($modelKey),
                    [
                        'resultsPerPage' => 0,
                        'filterQuery' => true, // NOTE: Filtering queries here could lead to issues with users having access to "outer" content types, but not those referenced from them. By filtering the query at this level, users editing an "outer" item could null out the reference they don't have access to...
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
                    $refs = data_get($laramieModel, $fieldKey);
                    if (is_array($refs)) {
                        $newRefs = [];
                        foreach ($refs as $ref) {
                            $newRefs[] = data_get($this->cachedItems, $ref, null);
                        }
                        array_filter($newRefs);
                        $laramieModel->{$fieldKey} = $newRefs;
                    } elseif ($refs) {
                        $laramieModel->{$fieldKey} = data_get($this->cachedItems, $refs, null);
                    }
                }
            }
        }

        return $laramieModels;
    }

    public function getMetaInformation($modelKey)
    {
        $model = $this->getModelByKey($modelKey);

        // Create the base query
        $query = DB::table('laramie_data')
            ->where('type', $model->getType());

        $this->augmentListQuery($query, $model, ['sort' => null]);

        $meta = (object) [];
        $meta->count = $query->count();

        $lastRecord = $query->orderBy('updated_at', 'desc')
            ->select(['updated_at', 'user_id'])
            ->first();

        if ($lastRecord) {
            $lastRecord->user = data_get(DB::table('users')
                ->where('id', $lastRecord->user_id)
                ->select([DB::raw(config('laramie.username').' as user')])
                ->first(), 'user');
        }

        $meta->user = data_get($lastRecord, 'user');
        $meta->updatedAt = data_get($lastRecord, 'updated_at');

        return $meta;
    }

    public function clearCache()
    {
        $this->cachedItems = [];
    }

    public function removeFromCache($id)
    {
        if ($id && array_key_exists($id, $this->cachedItems)) {
            unset($this->cachedItems[$id]);
        }
    }

    public function findById($model, $id = null, $maxPrefetchDepth = 5, $options = [])
    {
        $id = is_string($model) && LaramieHelpers::isValidUuid($model)
            ? $model
            : $id;

        if (array_key_exists($id, $this->cachedItems)) {
            return $this->cachedItems[$id];
        }

        if (is_string($model) && LaramieHelpers::isValidUuid($model)) {
            $model = data_get(DB::table('laramie_data')
                ->where('id', $model)
                ->select(['type'])
                ->first(), 'type');
        }

        $model = $this->getModelByKey($model);
        $factory = data_get($model, 'factory', LaramieModel::class);

        if ($id == 'new') {
            return $factory::load(null, true);
        }

        if ($id === null) {
            return null;
        }

        if (!LaramieHelpers::isValidUuid($id)) {
            throw new Exception('Id must be a valid UUID ' . $id);
        }

        $query = $this->getBaseQuery($model)
            ->where('id', $id);

        if (config('laramie.suppress_events') !== true && data_get($options, 'filterQuery', true) !== false) {
            Hook::fire(new FilterQuery($model, $query, $this->getUser(), $options));
        }

        $dbItem = $query->first();

        if ($dbItem === null) {
            return null;
        }

        $factory = $model->getFactory();

        $item = Arr::first($this->prefetchRelationships($model, [$factory::load($dbItem)], $maxPrefetchDepth, 0));

        // NOTE: we're only diving into aggregate relationships for single item
        // selection. What this means is that reference fields within deeply
        // nested aggregates won't be returned by `findByType`.
        $this->spiderAggregates($model, $item, $maxPrefetchDepth); // @TODO preston -- create common interface for aggregate fields and ModelSpec (FieldContainer)?

        $this->cachedItems[$item->id] = $item;

        $itemCollection = collect([$item]); // Wrap the single item in a collection to give `PostFetch` a consistent interface -- it works on collection-like items
        if (config('laramie.suppress_events') !== true) {
            Hook::fire(new PostFetch($model, $itemCollection, $this->getUser()));
        }

        return $item;
    }

    private function spiderAggregates($fieldContainer, $item, $maxPrefetchDepth) // @TODO preston -- create common interface for aggregate fields and ModelSpec (FieldContainer)?
    {
        $aggregateFields = collect($fieldContainer->getFieldsSpecs())
            ->filter(function ($e) {
                return $e->getType() === 'aggregate';
            })
            ->all();

        // Recursively dive into aggregate fields defined on the model (e.g.,
        // aggregate1 -> aggregate2 -> aggregate3 -> ...) `spiderAggregatesHelper`
        // is slightly different. It finds reference fields within aggregates and
        // hydrates them (which in turn is a recursive process).
        foreach ($aggregateFields as $aggregateKey => $aggregateField) {
            $aggregateData = data_get($item, $aggregateKey);
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
            $aggregateData = data_get($item, $aggregateKey, null);
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
                            $aggregateReferenceFieldData = data_get($aggregateData[$i], $aggregateReferenceFieldKey);
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
                        $aggregateReferenceFieldData = data_get($aggregateData, $aggregateReferenceFieldKey);
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

        return Arr::first([LaramieModel::load($query->first())]);
    }

    public function findItemRevisions($id)
    {
        if (LaramieHelpers::isValidUuid($id)) {
            return DB::table('laramie_data_archive as a')
                ->leftJoin('users', 'a.user_id', '=', 'users.id')
                ->where('laramie_data_id', $id)
                ->select(['a.id', 'a.updated_at', DB::raw(config('laramie.username').' as user')])
                ->orderBy('a.created_at', 'desc')
                ->get();
        }

        return [];
    }

    public function findPreviousItem($itemId, $olderThanRevisionId)
    {
        if (LaramieHelpers::isValidUuid($itemId)) {
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
        if (LaramieHelpers::isValidUuid($id)) {
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
        $shouldArchive = data_get(DB::select('select count(*) as count from laramie_data where id = ? and data != (select data from laramie_data_archive lda where laramie_data_id = laramie_data.id order by created_at desc limit 1)', [$archivedItem->laramie_data_id]), 0)->count > 0;
        if ($shouldArchive) {
            // Archive the primary item
            DB::statement('insert into laramie_data_archive (id, user_id, laramie_data_id, type, data, created_at, updated_at) select ?, user_id, id, type, data, ?, updated_at from laramie_data where id = ?', [Uuid::uuid1()->toString(), $this->getDbNow(), $archivedItem->laramie_data_id]);
        }
        // Update the primary item with the data from the revision we're restoring
        DB::statement('update laramie_data set updated_at = ?, user_id = (select user_id from laramie_data_archive where id = ?), data = (select data from laramie_data_archive where id = ?) where id = ?', [$this->getDbNow(), $id, $id, $archivedItem->laramie_data_id]);

        // Return the _archived_ item -- we'll use it in an alert message
        return $archivedItem;
    }

    public function deleteRevision($id)
    {
        if (LaramieHelpers::isValidUuid($id)) {
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
            ->where('type', $model->getType())
            ->addSelect('*');

        $computedFields = collect($model->getFieldsSpecs())
            ->filter(function ($field) {
                return $field->getType() == 'computed';
            })
            ->each(function ($field, $key) use ($query) {
                $query->addSelect(DB::raw(($field->isDeferred() ? 'null' : $field->getSql()).' as "'.$key.'"'));
            });

        return $query;
    }

    private function flattenRelationships($fieldHolder, $data)
    {
        if ($data == null) {
            return null;
        }

        foreach ($fieldHolder->fields as $key => $field) {
            if ($field->type == 'reference') {
                if ($field->subtype == 'single') {
                    $data->{$key} = is_string(data_get($data, $key)) && LaramieHelpers::isValidUuid($data->{$key})
                        ? $data->{$key}
                        : data_get($data, $key.'.id');
                } else {
                    $data->{$key} = collect(data_get($data, $key))
                        ->map(function ($e) {
                            return is_string($e) && LaramieHelpers::isValidUuid($e)
                                ? $e
                                : data_get($e, 'id');
                        })
                        ->filter()
                        ->values()
                        ->all();
                }
            } elseif ($field->type == 'file') {
                $data->{$key} = is_string(data_get($data, $key)) && LaramieHelpers::isValidUuid($data->{$key})
                    ? $data->{$key}
                    : data_get($data, $key.'.uploadKey');
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

    public function save($model, LaramieModel $laramieModel, $validateJson = true, $maxPrefetchDepth = 5, $runSaveHooks = true)
    {
        $item = null;

        DB::beginTransaction();
        try {
            $model = $this->getModelByKey($model);

            // Save a record of the original id. After saving, we'll reset the item's id back to the original so we have
            // context as to if the item is new in the PostSave event.
            $origId = $laramieModel->origId();

            /*
             * Fire pre-save event: listeners MUST be synchronous. This event
             * enables the ability to dynamically alter the model that will be
             * saved based on the injected arguments.
             */
            if ($runSaveHooks && config('laramie.suppress_events') !== true) {
                Hook::fire(new PreSave($model, $laramieModel, $this->getUser()));
            }

            $data = clone $laramieModel;
            $data->id = $data->id ?: data_get($data, '_metaId', Uuid::uuid1()->toString());

            $isUpdateTimestamps = (bool) data_get($data, 'timestamps', true);
            unset($data->{'timestamps'});

            $dbNow = $this->getDbNow();

            if ($isUpdateTimestamps || !$origId) {
                $data->updated_at = $dbNow;
            }

            if (!$origId) {
                // Insert
                $data->type = $model->getType();
                $data->created_at = data_get($data, 'created_at', $dbNow);
            } else if (array_key_exists($data->id, $this->cachedItems)) {
                unset($this->cachedItems[$data->id]);
            }

            // Relation fields are transformed into the id(s) of the items they
            // represent before being persisted in the db.
            $data = $this->flattenRelationships($model->toData(), $data); // @TODO preston -- create common interface for aggregate fields and ModelSpec (FieldContainer)?

            // Remove fields that aren't part of the the schema (old attributes
            // that may have been removed will still exist in archived versions of the
            // data). Basically what this means is that what gets saved in the db must
            // comply with the schema.
            $allowedFields = ['id', 'type', 'created_at', 'updated_at'];
            foreach ($model->getFieldsSpecs() as $key => $field) {
                if (!in_array($field->getType(), ['computed', 'html'])) {
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

            if ($validateJson) {
                // Perform JSON schema validation on the model. The real benefit of
                // this is when users are creating data outside of the admin (and saving
                // through this method). Validation is still happening to ensure that
                // what's getting saved adheres to a particular schema.
                $errors = [];
                $validator = new Validator();
                $validator->check($data, $model->getJsonValidator());
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
            $modelData['user_id'] = $this->getUserId();

            if ($origId) {
                // Update
                $archiveId = Uuid::uuid1()->toString();
                DB::statement('insert into laramie_data_archive (id, user_id, laramie_data_id, type, data, created_at, updated_at) select ?, user_id, id, type, data, ?, updated_at from laramie_data where id = ?', [$archiveId, $this->getDbNow(), $data->id]);
                DB::table('laramie_data')->where('id', $data->id)->update($modelData);
                // Delete the newly inserted archived version if it exactly matches the updated version.
                DB::statement('delete from laramie_data_archive where id = ? and data = (select data from laramie_data where id = ?)', [$archiveId, $data->id]);
            } else {
                // Insert
                $modelData['type'] = $model->getType();
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

            $item->_wasNew = !$origId;

            if ($runSaveHooks && config('laramie.suppress_events') !== true) {
                Hook::fire(new PostSave($model, $item, $this->getUser()));
            }

            DB::commit();
        } catch (\Illuminate\Database\QueryException $e) {
            DB::rollBack();
            throw config('app.debug')
                ? $e
                : new Exception('Error saving item');
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }

        return $item;
    }

    // `$isDeleteHistory` specifies whether or not to remove the item's history as well. If set to false, we'll create a snapshot of it in the archive table before deletion.
    public function deleteById($model, $id, $isDeleteHistory = false)
    {
        $model = $this->getModelByKey($model);
        $item = $this->findByIdSuperficial($model, $id);

        if (!LaramieHelpers::isValidUuid($id)) {
            return false;
        }

        \DB::beginTransaction();

        try {
            if (config('laramie.suppress_events') !== true) {
                Hook::fire(new PreDelete($model, $item, $this->getUser()));
            }

            if ($isDeleteHistory) {
                DB::table('laramie_data_archive')
                    ->where('laramie_data_id', $id)
                    ->delete();
            } else {
                DB::statement('insert into laramie_data_archive (id, user_id, laramie_data_id, type, data, created_at, updated_at) select ?, user_id, id, type, data, ?, updated_at from laramie_data where id = ?', [Uuid::uuid1()->toString(), $this->getDbNow(), $id]);
            }

            DB::table('laramie_data')
                ->where('id', $id)
                ->delete();

            if (config('laramie.suppress_events') !== true) {
                Hook::fire(new PostDelete($model, $item, $this->getUser()));
            }

            DB::commit();
        } catch (\Illuminate\Database\QueryException $e) {
            DB::rollBack();
            throw config('app.debug')
                ? $e
                : new Exception('Error deleting item');
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }

        return true;
    }

    public function cloneById($id)
    {
        $newId = Uuid::uuid1()->toString();
        DB::statement('insert into laramie_data (id, user_id, type, data, created_at, updated_at) select ?, ?, type, data, ?, ? from laramie_data where id = ?', [$newId, $this->getUser()->id, $this->getDbNow(), $this->getDbNow(), $id]);

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
            Hook::fire(new ModifyFileInfoPreSave($this->getUser(), $fileInfo));
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
        if (LaramieHelpers::isValidUuid($id)) {
            $laramieUpload = $this->getBaseQuery($this->getModelByKey('laramieUpload'))
                ->where('id', $id)
                ->first();

            return data_get($laramieUpload, 'id') ? json_decode($laramieUpload->data) : null;
        }

        return null;
    }

    public function removeFile($fileInfo)
    {
        // If there is no file to remove, return
        if (!$fileInfo || !data_get($fileInfo, 'uploadKey')) {
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

    private function getDbNow()
    {
        return Carbon::now()->toDateTimeString();
    }
}
