<?php

namespace Laramie\Lib;

use Laramie\Services\LaramieDataService;

class FieldSpec extends JsonBackedObject
{
/* Attributes that pertain to all field types */
    public function getId() { return $this->get('id'); }
    public function getType() { return $this->get('type'); }
    public function isListByDefault() { return $this->get('isListByDefault'); }
    public function isRequired() { return $this->get('isRequired'); }
    public function getLabel() { return $this->get('label'); }
    public function getLabelPlural() { return $this->get('labelPlural'); }
    public function getWeight() { return $this->get('weight'); }
    public function getExtra() { return $this->get('extra'); }
    public function getHelpText() { return $this->get('helpText'); }
    public function getValidation() { return $this->get('validation'); }
    public function isEditable() { return $this->get('isEditable'); }
    public function isListable() { return $this->get('isListable'); }
    public function isSearchable() { return $this->get('isSearchable'); }
    public function isSortable() { return $this->get('isSortable'); }
    public function isMetaField() { return $this->get('isMetaField'); }
    public function getShowWhen() { return $this->get('showWhen'); }
    public function getDefault() { return $this->get('default'); }
    public function getSortBy() { return $this->get('sortBy'); }

/* Computed field attributes */
    public function getDataType() { return $this->get('dataType'); }
    public function isDeferred() { return $this->get('isDeferred'); }
    public function getSql() { return $this->get('sql'); }

/* File field attributes */
    public function isPublic() { return $this->get('isPublic'); }
    public function getSubtype() { return $this->get('subtype'); }
    public function canChooseFromLibrary() { return $this->get('canChooseFromLibrary'); }
    public function isTypeSpecific() { return $this->get('isTypeSpecific'); }
    public function getDisk() { return $this->get('disk'); }

/* HTML field attributes */
    public function getHtml() { return $this->get('html'); }

/* Hidden field attributes */
    public function isVisibleOnEdit() { return $this->get('isVisibleOnEdit'); }

/* Currency / Number / Range field attributes */
    public function getSign() { return $this->get('sign'); }
    public function getMin() { return $this->get('min'); }
    public function getMax() { return $this->get('max'); }
    public function getStep() { return $this->get('step'); }
    public function isIntegerOnly() { return $this->get('isIntegerOnly'); }

/* Radio field attributes */
    public function getOptions() { return $this->get('options'); }

/* Reference field attributes */
    public function getRelatedModel() { return $this->get('relatedModel'); }
    // getSubtype defined in file field section

/* Select field attributes */
    // getOptions defined in radio section
    public function isSelect2() { return $this->get('isSelect2'); }
    public function isMultiple() { return $this->get('isMultiple'); }
    public function asRadio() { return $this->get('asRadio'); }

/* Aggregate field attributes */
    public function asTab() { return $this->get('asTab'); }
    public function getFields() { return $this->get('fields'); }
    public function isRepeatable() { return $this->get('isRepeatable'); }
    public function getMinItems() { return $this->get('minItems'); }
    public function getMaxItems() { return $this->get('maxItems'); }
    public function isUnwrap() { return $this->get('isUnwrap'); }
    public function isHideLabel() { return $this->get('isHideLabel'); }
}
