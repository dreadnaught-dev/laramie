<?php

namespace Laramie\Lib;

use DB;
use Exception;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
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

    private $searchOptions = [
        'preList' => false,
        'results-per-page' => 0,
    ];

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

    public function where($column, string $operator = null, $value = null, string $boolean = 'and')
    {
        $column = $this->translateColumn($column);
        $this->qb->where($column, $operator, $value, $boolean);

        return $this;
    }

    public function orWhere($column, string $operator = null, $value = null)
    {
        $column = $this->translateColumn($column);
        $this->qb->orWhere($column, $operator, $value);

        return $this;
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

    public function whereIn(string $column, $values, string $boolean = 'and', bool $not = false)
    {
        $column = $this->translateColumn($column);
        $this->qb->whereIn($sql, $bindings, $boolean);

        return $this;
    }

    public function orWhereIn(string $column, $values)
    {
        return $this->whereIn($column, $values, 'or');
    }

    public function whereNotIn(string $column, $values, string $boolean = 'and')
    {
        return $this->whereIn($column, $values, $boolean, true);
    }

    public function orWhereNotIn(string $column, $values)
    {
        return $this->whereIn($column, $values, 'or', true);
    }

    public function whereNull(string $column, string $boolean = 'and', bool $not = false)
    {
        $column = $this->translateColumn($column);
        $this->qb->whereNull($column, $boolean, $not);

        return $this;
    }

    public function orWhereNull(string $column)
    {
        return $this->whereNull($column, 'or');
    }

    public function whereNotNull(string $column, string $boolean = 'and')
    {
        return $this->whereNull($column, $boolean, true);
    }

    public function orWhereNotNull(string $column)
    {
        return $this->whereNull($column, 'or', true);
    }

    public function whereTag(string $tag)
    {
        return $this->whereRaw(DB::raw('(select 1 from laramie_data_meta ldm where ldm.laramie_data_id = laramie_data.id and ldm.type ilike ? and data->>\'text\' ilike ? limit 1) = 1'), ['tag', $tag]);
    }

    public function whereNotTag(string $tag)
    {
        return $this->whereRaw(DB::raw('(select 1 from laramie_data_meta ldm where ldm.laramie_data_id = laramie_data.id and ldm.type ilike ? and data->>\'text\' ilike ? limit 1) != 1'), ['tag', $tag]);
    }

    public function orderBy(string $column, string $direction = 'asc')
    {
        $this->searchOptions['sort'] = null;
        $column = $this->translateColumn($column);
        $this->qb->orderBy($column, $direction);

        return $this;
    }

    public function orderByDesc(string $column)
    {
        return $this->orderBy($column, 'desc');
    }

    public function orderByRaw(string $sql, array $bindings = [])
    {
        $this->qb->orderByRaw($sql, $bindings);

        return $this;
    }

    public function oldest(string $column = 'created_at')
    {
        $column = $this->translateColumn($column);
        $this->qb->oldest($column);

        return $this;
    }

    public function latest(string $column = 'created_at')
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

        $results = $this->dataService
            ->findByType($callingClass::getJsonClass(), $this->searchOptions, $this->queryCallback);

        // Convert the LaramieModel results to
        if ($results instanceof LengthAwarePaginator) {
            $results->setCollection(collect(array_map(function ($e) use ($callingClass) {
                return $callingClass::rehydrate($e);
            }, $results->items())));
        } elseif ($results instanceof Collection) {
            return $results->map(function ($e) use ($callingClass) {
                return $callingClass::rehydrate($e);
            });
        }

        return $results;
    }

    public function paginate(int $resultsPerPage = 15)
    {
        $this->searchOptions['results-per-page'] = max(0, $resultsPerPage);

        return $this->get();
    }

    public function first()
    {
        return $this->limit(1)->get()->first();
    }

    public function firstOrFail()
    {
        $item = $this->first();
        if (!object_get($item, 'id')) {
            throw new Exception('Item not found');
        }

        return $item;
    }

    public function find($id, $maxPrefetchDepth = 5)
    {
        return $this->callingClass::rehydrate($this->dataService
            ->findById($this->callingClass::getJsonClass(), $id, $maxPrefetchDepth));
    }

    public function findOrFail($id, $maxPrefetchDepth = 5)
    {
        $item = $this->find($id, $maxPrefetchDepth);
        if (!object_get($item, 'id')) {
            throw new Exception('Item not found');
        }

        return $item;
    }

    public function findSuperficial($id)
    {
        return $this->callingClass::rehydrate($this->dataService
            ->findByIdSuperficial($this->callingClass::getJsonClass(), $id));
    }

    public function deleteById($id, $isDeleteHistory = false)
    {
        return $this->dataService
            ->deleteById($this->callingClass::getJsonClass(), $id, $isDeleteHistory);
    }

    public function save(LaramieModel $item, $validate = true)
    {
        return $this->callingClass::rehydrate($this->dataService
            ->save($this->callingClass::getJsonClass(), $item, $validate));
    }

    // Allow updating of json data via query (may touch deeply-nested data as long as it's not computed, a reference field, or a repeatable aggregate (jsonb_set doesn't have decent facility for mass updating array data)
    public function update(array $attributes)
    {
        $attributesToUpdate = [];
        $jsonAttributes = [];

        $jsonModel = $this->dataService->getModelByKey($this->callingClass::getJsonClass());

        foreach ($attributes as $key => $value) {
            if (in_array($key, ['id', 'user_id', 'type', 'data', 'created_at', 'updated_at'])) {
                $attributesToUpdate[$key] = $value;
            } else {
                $path = preg_split('/(\.|=\>)/', $key);

                for ($i = 0, $fields = object_get($jsonModel, 'fields'); $i < count($path); ++$i ) {
                    $jsonField = object_get($fields, $path[$i]);

                    $fieldType = object_get($jsonField, 'type');

                    if ($fieldType === 'computed') {
                        throw new Exception('You may not update computed attributes.');
                    } elseif ($fieldType === 'reference') {
                        throw new Exception('You may not update `reference` attributes via `update()`.');
                    } elseif ($fieldType === 'aggregate') {
                        // Unfortunately, we can't update array values en-masse
                        if (object_get($jsonField, 'isRepeatable')) {
                            throw new Exception('You may not update aggregate arrays in this way (aggregate fields where `isRepeatable` is true).');
                        }
                        $fields = object_get($jsonField, 'fields'); // select aggregate's fields to dive into
                    }
                }

                // If we've gotten here, we can update the field.
                $jsonAttributes[$key] = $value;
            }
        }

        if (count($jsonAttributes) > 0) {
            $jsonSql = collect($jsonAttributes)
                ->map(function ($item, $key) {
                    return 'jsonb_set(data, \'{'.preg_replace('/(\.|=\>)/', ',', $key).'}\', \''.json_encode($item).'\', \'true\')';
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
    public function count(string $columns = '*')
    {
        return $this->getAggregateQuery()->count($columns);
    }

    public function max(string $column)
    {
        $column = $this->castColumnAsNumeric($column);

        return $this->castResultAsNumeric($this->getAggregateQuery()->max($column));
    }

    public function min(string $column)
    {
        $column = $this->castColumnAsNumeric($column);

        return $this->castResultAsNumeric($this->getAggregateQuery()->min($column));
    }

    public function avg(string $column)
    {
        $column = $this->castColumnAsNumeric($column);

        return $this->castResultAsNumeric($this->getAggregateQuery()->avg($column));
    }

    public function sum(string $column)
    {
        $column = $this->castColumnAsNumeric($column);

        return $this->castResultAsNumeric($this->getAggregateQuery()->sum($column));
    }

    /* META-RELATED FUNCTIONS */
    public function getTags($id)
    {
        return $this->dataService
            ->getTags($id);
    }

    public function addTag($id, $tag)
    {
        return $this->dataService
            ->createTag($id, $tag);
    }

    public function getComments($id)
    {
        return $this->dataService
            ->getComments($id);
    }

    public function addComment($id, $comment)
    {
        return $this->dataService
            ->createComment($this->id, $comment);
    }

    /* PRIVATE FUNCTIONS */
    private function getAggregateQuery()
    {
        $model = $this->dataService->getModelByKey($this->callingClass::getJsonClass());
        $query = $this->dataService->getBaseQuery($model);

        return $this->dataService->augmentListQuery($query, $model, array_merge($this->searchOptions, ['sort' => null]), $this->queryCallback);
    }

    private function castColumnAsNumeric(string $column)
    {
        return DB::raw('('.$this->translateColumn($column, false).')::numeric');
    }

    private function castResultAsNumeric($result)
    {
        return is_numeric($result)
            ? floor($result) == $result
                ? (int) $result
                : (float) $result
            : $result;
    }

    private function translateColumn($column, $isWrapInDBRaw = true)
    {
        if (gettype($column) == 'string') {
            $sql = $this->dataService->getSearchSqlFromFieldName($this->callingClass::getJsonClass(), $column);

            return $isWrapInDBRaw
                ? DB::raw($sql)
                : $sql;
        }

        return $column;
    }
}
