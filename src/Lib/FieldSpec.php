<?php

declare(strict_types=1);

namespace Laramie\Lib;

use Exception;
use Illuminate\Support\Collection;

class FieldSpec implements FieldContainer
{
    // common attributes that apply to all field types:
    public ?string $default;
    public string $extra;
    public string $helpText;
    public string $id; // @preston -- @todo should this be a uuid?
    public bool $isEditable = true;
    public bool $isListByDefault = true;
    public bool $isListable = true;
    public bool $isMetaField = false;
    public bool $isRequired = false;
    public bool $isSearchable = true;
    public bool $isSortable = true;
    public string $label;
    public string $labelPlural;
    public ?string $showWhen;
    public ?string $sortBy;
    public ?string $fieldName;
    public string $type;
    public string $validation;
    public int $weight;

    // "computed" field attributes
    public ?string $dataType;
    public ?bool $isDeferred;
    public ?string $listTemplate;
    public ?string $sql;

    // "file" field attributes
    public ?bool $canChooseFromLibrary;
    public ?string $disk;
    public ?bool $isPublic;
    public ?bool $isTypeSpecific;
    public ?string $subtype;

    // "html" field attributes
    public ?string $html;

    // "hidden" field attributes
    public ?bool $isVisibleOnEdit;

    // "currency" field attributes
    public ?string $currencyCode;

    // "number / range / currency " field attributes
    public ?float $max;
    public ?float $min;
    public ?float $step;
    public ?bool $isIntegerOnly;

    // "select / radio" field attributes
    public ?array $options;

    // "select" field attributes
    public ?bool $asRadio;
    public ?bool $isMultiple;
    public ?bool $isSelect2;

    // "reference" field attributes
    public ?string $relatedModel;
    public ?bool $hasMany;

    // "aggregate" field attributes
    public ?bool $asTab;
    public ?Collection $fields;
    public ?bool $isHideLabel;
    public ?bool $isRepeatable;
    public ?bool $isUnwrap;
    public ?int $maxItems;
    public ?int $minItems;

    private bool $isListed = false;

    public function __construct($jsonObject)
    {
        //$this->fields = collect([]);
        //$this->dataType = data_get($jsonObject, 'dataType', data_get($jsonObject, 'type'));
        //$this->isPublic = config('laramie.files_are_public_by_default', false);

        foreach ($jsonObject as $key => $value) {
            $value = match($key) {
                // attributes to default to true:
                'asTab',
                'canChooseFromLibrary',
                'isEditable',
                'isListByDefault',
                'isListable',
                'isRepeatable',
                'isSearchable',
                'isSortable',
                'isVisibleOnEdit' => $value !== false,

                // attributes to default to false
                'asRadio',
                'isDeferred',
                'isHideLabel',
                'isIntegerOnly',
                'isMetaField',
                'isMultiple',
                'isPublic',
                'isRequired',
                'isSelect2 ',
                'isTypeSpecific',
                'isUnwrap' => $value === true,

                'fields' => $value
                    ? collect($value)->map(function($item) { return new FieldSpec($item); })
                    : collect((object) []),

                default => $value,
            };

            $this->{$key} = $value;
        }
    }

    public function __call($name, $arguments)
    {
        if (property_exists($this, $name)) {
            return $this->{$name};
        } elseif (strpos($name, 'get') === 0) {
            $attributeName = \Str::camel(substr($name, 3));
            if (property_exists($this, $attributeName)) {
                return $this->{$attributeName};
            }
        }

        throw new Exception("Property [{$name}] does not exist");
    }

    public function setIsListed(bool $value)
    {
        $this->isListed = $value;
    }

    public function getIsListed() : bool
    {
        return $this->isListed === true;
    }

    public function getType()
    {
        return $this->type;
    }

    public function getFields()
    {
        return $this->fields;
    }

    public function getField($fieldName)
    {
        return $this->get($this->getFields(), $fieldName);
    }

    public function addField(string $key, object $fieldInfo)
    {
        $this->getFields()->{$key} = ModelLoader::processField($key, $fieldInfo);
    }

    public function getTemplate()
    {
        return preg_replace('/(^_+|_+$)/', '', preg_replace('/_+/', '_', preg_replace('/\{\{[^}]+\}\}/', '', $this->id)));
    }
}
