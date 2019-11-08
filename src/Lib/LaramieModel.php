<?php

namespace Laramie\Lib;

use Exception;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Ramsey\Uuid\Uuid;

/**
 * LaramieModel makes JSON data stored in dbo.laramie_data accessible (and has
 * helpers for converting it back to JSON).
 *
 * In some ways LaramieModels look kind of like Eloquent ones. They are not.
 * LaramieModels are **not** also query builders (as Eloquent models are).
 * Similarities are merely syntactic sugar to smooth the cognitive burden of
 * working with Laramie-backed data. It is a crude pastiche of eloquence, if you
 * will... That said, extending this class for your custom ones may be useful (or
 * feel free to use LaramieDataService directly if you'd rather).
 *
 * Note that the admin is never going to use your extended model when editing.
 * The admin will always use the standard LaramieModel.
 */
class LaramieModel implements \JsonSerializable
{
    public $id = null;
    public $user_id = null;
    public $type = null;
    public $data = null;
    public $created_at = null;
    public $updated_at = null;
    public $_isNew = true;
    public $_isUpdate = false;

    protected static $dataService = null;

    protected $tableFields = ['id' => 1, 'user_id' => 1, 'type' => 1, 'data' => 1, 'created_at' => 1, 'updated_at' => 1];
    protected $excludeAttributesOnSave = ['jsonClass' => 1, 'tableFields' => 1, 'excludeAttributesOnSave' => 1];
    protected $jsonClass = null;

    /**
     * Load data and return a view-model mapped version of `data`.
     *
     * @param mixed $data
     *                                            $data can be one of the following types:
     *                                            LengthAwarePaginator object (e.g., DB::table('users')->paginate(20))
     *                                            Array of stdClass objects (e.g., DB::table('users')->get())
     *                                            stdClass (e.g., DB::table('users')->first())
     * @param bool  $isReturnEmptyModelIfNullData
     *
     * @return static[]|static
     *
     * @throws Exception
     */
    public static function load($data, $isReturnEmptyModelIfNullData = true)
    {
        if ($data instanceof LengthAwarePaginator) {
            $data->setCollection(collect(array_map(function ($e) {
                return self::hydrate($e);
            }, $data->items())));
        } elseif ($data instanceof Collection) {
            return $data->map(function ($e) {
                return self::hydrate($e);
            });
        } elseif (is_array($data)) {
            $data = array_map(function ($e) {
                return self::hydrate($e);
            }, $data);
        } elseif ($data !== null && is_object($data)) {
            $data = self::hydrate($data);
        } elseif ($isReturnEmptyModelIfNullData && $data === null) {
            $data = self::hydrate((object) []);
        } else {
            throw new Exception('LaramieModel received invalid data type to load');
        }

        return $data;
    }

    public static function loadOrFail($data)
    {
        return self::load($data, false);
    }

    /**
     * Factory: return instance of LaramieModel class, populated with
     * attributes from `$data`.
     *
     * @param \stdClass $data
     *
     * @return static
     */
    public static function hydrate(\stdClass $data)
    {
        // Using static instead of self. For why, see: http://php.net/oop5.late-static-bindings and
        // http://stackoverflow.com/questions/8583989/php-call-superclass-factory-method-from-subclass-factory-method
        $tmp = new static();
        $tmp->fill($data);

        $tmp->hydrated($data);

        return $tmp;
    }

    /**
     * Factory: return instance of extended LaramieModel class, populated with
     * attributes from `$data`.
     *
     * @param \stdClass $data
     *
     * @return static
     */
    public static function hydrateWithModel(LaramieModel $data)
    {
        $tmp = new static();

        foreach ($data as $key => $value) {
            $tmp->{$key} = $value;
        }

        $tmp->hydrated($data);

        return $tmp;
    }

    public function hydrated($data) { }

    /**
     * Hydrate `$data` into a LaramieModel -- take json data and hoist its
     * values up to the level of `$this`.
     *
     * @param \stdClass|array $data
     *
     * @return $this
     */
    public function fill($data)
    {
        foreach ($data as $key => $value) {
            $this->{$key} = $value;
        }

        $this->_origId = $this->id;
        $this->_isNew = $this->id == null || !Uuid::isValid($this->id);
        $this->_isUpdate = !$this->_isNew;
        $this->_origData = $this->data;

        $origData = $this->data;
        unset($this->data);

        $tmp = json_decode($origData);

        if (gettype($tmp) == 'object') {
            foreach ($tmp as $key => $value) {
                $this->{$key} = $value;
                // Don't overwrite protected fields with values from JSON data.
                if (array_key_exists($key, $this->tableFields)) {
                    continue;
                }
            }
        } else {
            $this->data = (object) [];
        }

        return $this;
    }

    /**
     * Convert data stored in this object into a json encoded string (with the
     * exception of certain other table-level data).
     *
     * @return mixed[] $modelData (contains json-encoded data representing the bulk of `$this`)
     */
    public function toArray()
    {
        $properties = get_object_vars($this);
        $modelData = [];
        $jsonData = [];
        foreach ($properties as $key => $value) {
            if (array_key_exists($key, $this->tableFields)) {
                $modelData[$key] = $value;
            } elseif (strpos($key, '_') === 0 || array_key_exists($key, $this->excludeAttributesOnSave)) {
                // Skip fields that start with an underscore
                continue;
            } else {
                $jsonData[$key] = $value;
            }
        }

        $modelData['data'] = json_encode((object) $jsonData);

        return $modelData;
    }

    protected static function getLaramieQueryBuilder($functionToInvoke, $arguments = [])
    {
        $builder = new LaramieQueryBuilder(new static());

        return call_user_func_array([$builder, $functionToInvoke], $arguments);
    }

    /**
     * Apply an Eloquent-ish veneer to interacting with the data service. The
     * following methods are NOT eloquent, merely helpful.
     */
    final public static function where($column, string $operator = null, $value = null, string $boolean = 'and')
    {
        return static::getLaramieQueryBuilder('where', func_get_args());
    }

    final public static function whereRaw(string $sql, $bindings = [], string $boolean = 'and')
    {
        return static::getLaramieQueryBuilder('whereRaw', func_get_args());
    }

    final public static function whereIn(string $column, $values, string $boolean = 'and', bool $not = false)
    {
        return static::getLaramieQueryBuilder('whereIn', func_get_args());
    }

    final public static function whereNotIn(string $column, $values, string $boolean = 'and')
    {
        return static::getLaramieQueryBuilder('whereNotIn', func_get_args());
    }

    final public static function whereNull(string $column, string $boolean = 'and', bool $not = false)
    {
        return static::getLaramieQueryBuilder('whereNull', func_get_args());
    }

    final public static function whereNotNull(string $column, string $boolean = 'and')
    {
        return static::getLaramieQueryBuilder('whereNotNull', func_get_args());
    }

    final public static function whereTag($tag)
    {
        return static::getLaramieQueryBuilder('whereTag', [$tag]);
    }

    final public static function whereNotTag($tag)
    {
        return static::getLaramieQueryBuilder('whereNotTag', [$tag]);
    }

    final public static function orderBy(string $field, string $direction = 'asc')
    {
        return static::getLaramieQueryBuilder('orderBy', func_get_args());
    }

    final public static function orderByDesc(string $field)
    {
        return static::getLaramieQueryBuilder('orderByDesc', func_get_args());
    }

    final public static function orderByRaw(string $sql, array $bindings = [])
    {
        return static::getLaramieQueryBuilder('orderByRaw', func_get_args());
    }

    final public static function latest(string $column = 'created_at')
    {
        return static::getLaramieQueryBuilder('latest', func_get_args());
    }

    final public static function oldest(string $column = 'created_at')
    {
        return static::getLaramieQueryBuilder('oldest', func_get_args());
    }

    final public static function inRandomOrder(string $seed = '')
    {
        return static::getLaramieQueryBuilder('orderBy', func_get_args());
    }

    final public static function skip(int $val)
    {
        return static::getLaramieQueryBuilder('skip', func_get_args());
    }

    final public static function offset(int $val)
    {
        return static::getLaramieQueryBuilder('offset', func_get_args());
    }

    final public static function take(int $val)
    {
        return static::getLaramieQueryBuilder('take', func_get_args());
    }

    final public static function limit(int $val)
    {
        return static::getLaramieQueryBuilder('limit', func_get_args());
    }

    final public static function all()
    {
        return static::getLaramieQueryBuilder('get');
    }

    final public static function get()
    {
        return static::getLaramieQueryBuilder('get');
    }

    final public static function paginate(int $resultsPerPage = 15)
    {
        return static::getLaramieQueryBuilder('paginate', func_get_args());
    }

    final public static function first()
    {
        return static::getLaramieQueryBuilder('first');
    }

    final public static function firstOrFail()
    {
        return static::getLaramieQueryBuilder('firstOrFail');
    }

    final public static function singular()
    {
        return static::getLaramieQueryBuilder('singular');
    }

    final public static function find($id, $maxPrefetchDepth = 5)
    {
        return static::getLaramieQueryBuilder('find', func_get_args());
    }

    final public static function findSuperficial($id)
    {
        return static::getLaramieQueryBuilder('findSuperficial', func_get_args());
    }

    final public static function findOrFail($id, $maxPrefetchDepth = 5)
    {
        return static::getLaramieQueryBuilder('findOrFail', func_get_args());
    }

    final public static function count(string $columns = '*')
    {
        return static::getLaramieQueryBuilder('count', func_get_args());
    }

    final public static function min(string $column)
    {
        return static::getLaramieQueryBuilder('min', func_get_args());
    }

    final public static function max(string $column)
    {
        return static::getLaramieQueryBuilder('max', func_get_args());
    }

    final public static function sum(string $column)
    {
        return static::getLaramieQueryBuilder('sum', func_get_args());
    }

    final public static function avg(string $column)
    {
        return static::getLaramieQueryBuilder('avg', func_get_args());
    }

    final public static function average(string $column)
    {
        return self::avg();
    }

    // Save new item and return instance
    final public static function create(array $attributes, $validate = true)
    {
        $item = self::load((object) $attributes);

        return static::getLaramieQueryBuilder('save', [$item, $validate]);
    }

    public function save($validate = true)
    {
        $updatedItem = static::getLaramieQueryBuilder('save', [$this, $validate]);

        foreach ($updatedItem as $key => $value) {
            $this->{$key} = $value;
        }

        return $this;
    }

    public function update(array $attributes = [], $validate = true)
    {
        foreach ($attributes as $key => $value) {
            data_set($this, $key, $value);
        }

        return $this->save($validate);
    }

    public function delete($isDeleteHistory = false)
    {
        return static::getLaramieQueryBuilder('deleteById', [$this->id, $isDeleteHistory]);
    }

    final public static function newModelInstance(array $attributes = [])
    {
        return new static();
    }

    final public static function destroy($ids, $isDeleteHistory)
    {
        $ids = is_array($ids) ? $ids : [$ids];

        foreach ($ids as $id) {
            return static::getLaramieQueryBuilder('deleteById', [$this->id, $isDeleteHistory]);
        }
    }

    public function getComments()
    {
        return static::getLaramieQueryBuilder('getComments', [$this->id]);
    }

    public function getTags()
    {
        return static::getLaramieQueryBuilder('getTags', [$this->id]);
    }

    public function addTag($tag)
    {
        // @todo -- case where id is null (or item is new)
        return static::getLaramieQueryBuilder('addTag', [$this->id, $tag]);
    }


    public function addComment($comment)
    {
        // @todo -- case where id is null (or item is new)
        return static::getLaramieQueryBuilder('addComment', [$this->id, $comment]);
    }

    public static function depth($maxPrefetchDepth)
    {
        return static::getLaramieQueryBuilder('depth', [$maxPrefetchDepth]);
    }

    public static function superficial()
    {
        return static::depth(0);
    }

    /**
     * Get the name of the model's json counterpart. Naming convention is
     * assumed camelCase in JSON. Can be explicitly defined by providing
     * `$jsonClass` override.
     *
     * @return string $jsonClass name
     */
    final public static function getJsonClass()
    {
        $tmp = new static();

        if ($tmp->jsonClass) {
            return $tmp->jsonClass;
        }

        return lcfirst(class_basename(get_called_class())); // `class_basename` is a Laravel Helper
    }

    public function jsonSerialize() {
        foreach ($this as $key => $value) {
            if (strpos($key, '_') === 0) {
                unset($this->{$key});
            }
        }
        return $this;
    }
}
