<?php

namespace Laramie\Lib;

use Exception;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Ramsey\Uuid\Uuid;

/**
 * LaramieModel makes JSON data stored in dbo.laramie_data accessible (and has
 * helpers for converting it back to JSON).
 */
class LaramieModel
{
    public $id = null;
    public $user_id = null;
    public $type = null;
    public $data = null;
    public $created_at = null;
    public $updated_at = null;
    public $_isNew = true;
    public $_isUpdate = false;

    public static $tableColumns = ['id' => 1, 'user_id' => 1, 'type' => 1, 'data' => 1, 'created_at' => 1, 'updated_at' => 1];

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

        return $tmp;
    }

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

        $origData = $this->data;
        unset($this->data);

        $protectedFields = $this->getProtectedFieldHash();

        $tmp = json_decode($origData);

        if (gettype($tmp) == 'object') {
            foreach ($tmp as $key => $value) {
                $this->{$key} = $value;
                // Don't overwrite protected fields with values from JSON data.
                if (array_key_exists($key, $protectedFields)) {
                    continue;
                }
            }
        } else {
            $this->data = (object) [];
        }

        return $this;
    }

    /**
     * Set and return a map of fields that should _not_ be converted to json
     * (id, created_at, updated_at, etc).
     *
     * @return mixed[] $protectedFields
     */
    private function getProtectedFieldHash()
    {
        return self::$tableColumns;
    }

    /**
     * Set and return a map of fields that should _not_ be converted to json
     * (id, created_at, updated_at, etc).
     *
     * @return mixed[] $protectedFields
     */
    public static function addTableColumn($columnName)
    {
        self::$tableColumns[] = $columnName;
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
        $protectedFields = $this->getProtectedFieldHash();
        $modelData = [];
        $jsonData = [];
        foreach ($properties as $key => $value) {
            if (array_key_exists($key, $protectedFields)) {
                $modelData[$key] = $value;
            } elseif (strpos($key, '_') === 0) {
                // Skip fields that start with an underscore
                continue;
            } else {
                $jsonData[$key] = $value;
            }
        }

        $modelData['data'] = json_encode((object) $jsonData);

        return $modelData;
    }
}
