<?php

declare(strict_types=1);

namespace Laramie\Lib;

use Exception;
use Illuminate\Support\Collection;

class ModelSchema implements FieldContainer
{
    public array $additionalBulkActions = [];
    public string $addNewText = 'Add New';
    public string $alias = 'alias';
    public array $bulkActions = [];
    public string $defaultSort = 'created_at';
    public string $defaultSortDirection = 'desc';
    public array $editCss = [];
    public array $editJs = [];
    public string $editView = 'laramie::edit-page';
    public string $factory;
    public Collection $fields;
    public bool $isDeletable = true;
    public bool $isDisableMeta = false;
    public bool $isDisableRevisions = false;
    public bool $isEditable = true;
    public bool $isListable = true;
    public bool $isSingular = false;
    public bool $isSystemModel = false;
    public array $listCss = [];
    public array $listJs = [];
    public string $listView = 'laramie::list-page';
    public string $mainTabLabel = 'Main';
    public string $name;
    public string $namePlural;
    public array $quickSearch = [];
    public array $refs = [];
    public string $type;
    public object $jsonValidationSchema;

    public function __construct($jsonObject)
    {
        $this->bulkActions = config('laramie.default_bulk_actions');
        $this->factory = LaramieModel::class;
        $this->fields = collect([]);

        foreach ($jsonObject as $key => $value) {
            if (!property_exists($this, $key)) {
                $this->{$key} = $value;
            }

            $value = match($key) {
                'addNewText' => $value ?: 'Add New',
                'bulkActions' => $value ?: config('laramie.default_bulk_actions'),
                'editCss' => $value ?: [],
                'editJs' => $value ?: [],
                'editView' => $value ?: 'laramie::edit-page',
                'factory' => $value ?: LaramieModel::class,
                'fields' => $value ? collect($value)->map(function($item) { return new FieldSpec($item); }) : collect([]),
                'isDeletable' => $value !== false, // default to true
                'isDisableMeta' => $value === true, // default to false
                'isDisableRevisions' => $value === true, // default to false
                'isEditable' => $value !== false, // default to true
                'isListable' => $value !== false, // default to true
                'isSingular' => $value === true, // default to false
                'isSystemModel' => $value === true, // default to false
                'listCss' => $value ?: [],
                'listJs' => $value ?: [],
                'mainTabLabel' => $value ?: 'Main',
                //'refs' => collect($value ?: [])
                    //->map(function ($item) {
                        //return new RefSpec($item);
                    //})
                    //->toArray(),
                default => $value,
            };

            $this->{$key} = $value;
        }
    }

    //public function __call($name, $arguments)
    //{
        //if (property_exists($this, $name)) {
            //return $this->{$name};
        //} elseif (strpos($name, 'get') === 0) {
            //$attributeName = \Str::camel(substr($name, 3));
            //if (property_exists($this, $attributeName)) {
                //return $this->{$attributeName};
            //}
        //}

        //throw new Exception("Property [{$name}] does not exist");
    //}

    public function getType()
    {
        return $this->type;
    }

    public function getJsonValidationSchema()
    {
        return $this->jsonValidationSchema;
    }

    public function setJsonValidationSchema($schema)
    {
        return $this->jsonValidationSchema = $schema;
    }

    public function getFields()
    {
        return $this->fields;
    }

    public function getField($fieldName)
    {
        return data_get($this->getFields(), $fieldName);
    }

    public function addField(string $key, object $fieldInfo)
    {
        $this->getFields()->{$key} = ModelLoader::processField($key, $fieldInfo);
    }
}
