<?php

declare(strict_types=1);

namespace Laramie\Lib;

use DB;
use Exception;
use Illuminate\Database\Query\Expression;
use Laramie\Services\LaramieDataService;

/**
 * LaramieQueryBuilder is a pastiche of the venerable QueryBuilder (part of
 * normal eloquent models). LaramieModels are in no way `Eloquent`, but some
 * Eloquent-ish veneer has been added to smooth the transition from Eloquent to
 * Laramie.
 */
class LaramieQueryBuilder
{
    protected $callingClass;

    protected $qb;

    protected $searchOptions = [
        'filterQuery' => true,
        'resultsPerPage' => 0,
    ];

    protected $maxPrefetchDepth = 3;
    protected $isSpiderAggregates = false;

    public function __construct($callingClass)
    {
        $this->callingClass = $callingClass;
        $this->dataService = app(LaramieDataService::class);
        $this->qb = DB::table('__dummy');
        $this->queryCallback = function ($query) {
            $query->mergeWheres($this->qb->wheres, $this->qb->getBindings());

            foreach (($this->qb->orders ?: []) as $order) {
                $query->orderBy($order['column'], $order['direction']);
            }

            if ($this->qb->limit) {
                $query->limit($this->qb->limit);
            }

            if ($this->qb->offset) {
                $query->offset($this->qb->offset);
            }
        };
    }

    public function query()
    {
        return $this;
    }

    public function depth($maxPrefetchDepth)
    {
        $this->maxPrefetchDepth = $maxPrefetchDepth;

        return $this;
    }

    public function filterQuery(bool $isFilterQuery)
    {
        return $this->setOption('filterQuery', $isFilterQuery);
    }

    public function getFilteredQueryBuilder()
    {
        $model = $this->dataService->getModelByKey($this->callingClass::getJsonClass());
        $query = $this->dataService->getBaseQuery($model);

        return $this->dataService->augmentListQuery($query, $model, array_merge($this->searchOptions, ['sort' => null]), $this->queryCallback);
    }

    public function setOption(string $optionName, $optionValue)
    {
        $this->searchOptions[$optionName] = $optionValue;

        return $this;
    }

    public function spiderAggregates($isSpiderAggregates = true)
    {
        $this->isSpiderAggregates = $isSpiderAggregates;

        return $this;
    }

    public function where($column, $operator = null, $value = null, string $boolean = 'and')
    {
        // If the where is an array, assume it contains kvps that should be added:
        if (is_array($column)) {
            $this->qb->where(function ($query) use ($column) {
                foreach ($column as $arrKey => $arrValue) {
                    $column = $this->translateColumn($arrKey, $arrValue);
                    $query->where($column, '=', $arrValue);
                }
            });
        } else {
            // If the operator is `=`, we can use the generally more performant gin index search.
            if (
                is_string($column) // don't operate on DB::raw columns
                && ($operator === '=' || $value === null) // only operate on equality search
                && !array_key_exists($column, config('laramie.laramie_data_fields')) // don't operate on concrete columns
                && !preg_match('/\bdata\b/', $column) // don't operate on columns that may be accessing nested data props
            ) {
                $this->qb->where('data', '@>', DB::raw('\'{"'.$column.'":'.json_encode($value ?: $operator).'}\'')); // $value ?: $operator, $boolean);
            } else {
                $column = $this->translateColumn($column, $value ?: $operator);
                $this->qb->where($column, $operator, $value, $boolean);
            }
        }

        return $this;
    }

    public function orWhere($column, string $operator = null, $value = null)
    {
        return $this->where($column, $operator, $value, 'or');
    }

    public function whereRaw(string $sql, $bindings = [], string $boolean = 'and')
    {
        $this->qb->whereRaw($sql, $bindings, $boolean);

        return $this;
    }

    public function orWhereRaw(string $sql, $bindings = [])
    {
        $this->qb->orWhereRaw($sql, $bindings);

        return $this;
    }

    public function whereIn($column, $values, string $boolean = 'and', bool $not = false)
    {
        $column = $this->translateColumn($column);
        $this->qb->whereIn($column, $values, $boolean, $not);

        return $this;
    }

    public function orWhereIn($column, $values)
    {
        return $this->whereIn($column, $values, 'or');
    }

    public function whereNotIn($column, $values, string $boolean = 'and')
    {
        return $this->whereIn($column, $values, $boolean, true);
    }

    public function orWhereNotIn($column, $values)
    {
        return $this->whereIn($column, $values, 'or', true);
    }

    public function whereNull($column, string $boolean = 'and', bool $not = false)
    {
        $column = $this->translateColumn($column);
        $this->qb->whereNull($column, $boolean, $not);

        return $this;
    }

    public function orWhereNull($column)
    {
        return $this->whereNull($column, 'or');
    }

    public function whereNotNull($column, string $boolean = 'and')
    {
        return $this->whereNull($column, $boolean, true);
    }

    public function orWhereNotNull($column)
    {
        return $this->whereNull($column, 'or', true);
    }

    public function whereTag(string $tag)
    {
        return $this->whereRaw(DB::raw('(select 1 from laramie_data ldm where ldm.data->\'relatedItemId\' = laramie_data.id and ldm.type ilike ? and data->>\'text\' ilike ? limit 1) = 1'), ['laramieTag', $tag]);
    }

    public function whereNotTag(string $tag)
    {
        return $this->whereRaw(DB::raw('(select 1 from laramie_data ldm where ldm.data->\'relatedItemId\' = laramie_data.id and ldm.type ilike ? and data->>\'text\' ilike ? limit 1) != 1'), ['laramieTag', $tag]);
    }

    public function orderBy($column, string $direction = 'asc')
    {
        $this->searchOptions['sort'] = null;
        $column = $this->translateColumn($column);
        $this->qb->orderBy($column, $direction);

        return $this;
    }

    public function orderByDesc($column)
    {
        return $this->orderBy($column, 'desc');
    }

    public function orderByRaw(string $sql, array $bindings = [])
    {
        $this->qb->orderByRaw($sql, $bindings);

        return $this;
    }

    public function oldest($column = 'created_at')
    {
        $column = $this->translateColumn($column);
        $this->qb->oldest($column);

        return $this;
    }

    public function latest($column = 'created_at')
    {
        $column = $this->translateColumn($column);
        $this->qb->latest($column);

        return $this;
    }

    public function inRandomOrder(string $seed = '')
    {
        $this->qb->inRandomOrder($seed);

        return $this;
    }

    public function offset(int $val)
    {
        $this->qb->offset($val);

        return $this;
    }

    public function skip(int $val)
    {
        return $this->offset($val);
    }

    public function limit(int $val)
    {
        $this->qb->limit($val);

        return $this;
    }

    public function take(int $val)
    {
        return $this->limit($val);
    }

    public function get()
    {
        $callingClass = $this->callingClass;

        $this->searchOptions['factory'] = get_class($callingClass);

        $results = $this->dataService
            ->findByType($callingClass::getJsonClass(), $this->searchOptions, $this->queryCallback, $this->maxPrefetchDepth, 0, $this->isSpiderAggregates);

        return $results;
    }

    public function paginate(int $resultsPerPage = null)
    {
        $resultsPerPage = $resultsPerPage === null
            ? config('laramie.results_per_page')
            : $resultsPerPage;

        $this->searchOptions['resultsPerPage'] = max(0, $resultsPerPage);

        return $this->get();
    }

    public function first()
    {
        return $this->limit(1)->get()->first();
    }

    public function firstOrFail()
    {
        $item = $this->first();
        if (!data_get($item, 'id')) {
            throw new Exception('Item not found');
        }

        return $item;
    }

    public function singular()
    {
        $id = $this->dataService->getSingularItemId($this->callingClass::getJsonClass());

        return $this->find($id, $this->maxPrefetchDepth);
    }

    public function find($id)
    {
        if (!$id) {
            return null;
        }

        $item = $this->dataService->findById($this->callingClass::getJsonClass(), $id, $this->maxPrefetchDepth);

        if (!$item) {
            return null;
        }

        return $this->callingClass::hydrateWithModel($item);
    }

    public function findOrFail($id)
    {
        $item = $this->find($id, $this->maxPrefetchDepth);
        if (!data_get($item, 'id')) {
            throw new Exception('Item not found');
        }

        return $item;
    }

    public function findSuperficial($id)
    {
        return $this->callingClass::hydrateWithModel($this->dataService
            ->findByIdSuperficial($this->callingClass::getJsonClass(), $id));
    }

    public function superficial()
    {
        return $this->depth(0);
    }

    public function deleteById($id, $isDeleteHistory = false, $type = null)
    {
        return $this->dataService
            ->deleteById($type ?: $this->callingClass::getJsonClass(), $id, $isDeleteHistory);
    }

    public function save(LaramieModel $item, $validate = true, $runSaveHooks = true)
    {
        return $this->callingClass::hydrateWithModel($this->dataService
            ->save($this->callingClass::getJsonClass(), $item, $validate, $this->maxPrefetchDepth, $runSaveHooks));
    }

    // Allow updating of json data via query (may touch deeply-nested data as long as it's not computed, a reference field, or a repeatable aggregate (jsonb_set doesn't have ability to mass update array data)
    public function update(array $attributes)
    {
        $attributesToUpdate = [];
        $jsonAttributes = [];

        $model = $this->dataService->getModelByKey($this->callingClass::getJsonClass());

        foreach ($attributes as $key => $value) {
            if (in_array($key, ['id', 'user_id', 'type', 'data', 'created_at', 'updated_at'])) {
                $attributesToUpdate[$key] = $value;
            } else {
                $path = preg_split('/(\.|=\>)/', $key);

                // @preston -- stopped here. convert all references of getFields to getFields and update all fields iterated over to use FieldSpec methods.
                // Once done, rename getFieldSpec and getFields to getField and getFields.
                for ($i = 0, $fields = $model->getFields(); $i < count($path); $i++) {
                    $field = data_get($fields, $path[$i]);

                    $fieldType = $field->getType();

                    if ($fieldType === 'computed') {
                        throw new Exception('You may not update computed attributes.');
                    } elseif ($fieldType === 'reference') {
                        throw new Exception('You may not update `reference` attributes via `update()`.');
                    } elseif ($fieldType === 'aggregate') {
                        // Unfortunately, we can't update array values en-masse
                        if (data_get($field, 'isRepeatable')) {
                            throw new Exception('You may not update repeatable aggregate fields this way.');
                        }
                        $fields = $field->getFields(); // select aggregate's fields to dive into
                    }
                }

                // If we've gotten here, we can update the field.
                $jsonAttributes[$key] = $value;
            }
        }

        if (count($jsonAttributes) > 0) {
            $jsonSql = collect($jsonAttributes)
                ->map(function ($item, $key) {
                    return 'jsonb_set(data, \'{'.preg_replace('/(\.|=\>)/', ',', $key).'}\', \''.str_replace("'", "''", json_encode($item)).'\', \'true\')';
                })
                ->reduce(function ($carry, $item) {
                    return str_replace('jsonb_set(data', 'jsonb_set('.$carry, $item);
                }, 'data');

            $attributesToUpdate['data'] = DB::raw($jsonSql);
        }

        if (count($attributesToUpdate) > 0) {
            return $this->getAggregateQuery()->update($attributesToUpdate);
        }

        return 0;
    }

    public function delete()
    {
        return $this->getAggregateQuery()->delete();
    }

    /* AGGREGATE FUNCTIONS */
    public function count($columns = '*')
    {
        return $this->getAggregateQuery()->count($columns);
    }

    public function max($column)
    {
        $column = $this->castColumnAsNumeric($column);

        return $this->castResultAsNumeric($this->getAggregateQuery()->max($column));
    }

    public function min($column)
    {
        $column = $this->castColumnAsNumeric($column);

        return $this->castResultAsNumeric($this->getAggregateQuery()->min($column));
    }

    public function avg($column)
    {
        $column = $this->castColumnAsNumeric($column);

        return $this->castResultAsNumeric($this->getAggregateQuery()->avg($column));
    }

    public function sum($column)
    {
        $column = $this->castColumnAsNumeric($column);

        return $this->castResultAsNumeric($this->getAggregateQuery()->sum($column));
    }

    /* PRIVATE FUNCTIONS */
    private function getAggregateQuery()
    {
        $model = $this->dataService->getModelByKey($this->callingClass::getJsonClass());
        $query = $this->dataService->getBaseQuery($model);

        return $this->dataService->augmentListQuery($query, $model, array_merge($this->searchOptions, ['sort' => null]), $this->queryCallback);
    }

    private function castColumnAsNumeric($column)
    {
        return DB::raw('('.$this->translateColumn($column, null, false).')::numeric');
    }

    private function castResultAsNumeric($result)
    {
        return is_numeric($result)
            ? floor($result) == $result
                ? (int) $result
                : (float) $result
            : $result;
    }

    protected function translateColumn($column, $value = null, $isWrapInDBRaw = true)
    {
        if ($column instanceof Expression) {
            return $column;
        }

        if (gettype($column) == 'string') {
            $sql = $this->dataService->getSearchSqlFromFieldName($this->callingClass::getJsonClass(), $column, $value);

            return $isWrapInDBRaw
                ? DB::raw($sql)
                : $sql;
        }

        return $column;
    }
}
