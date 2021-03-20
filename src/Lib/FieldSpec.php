<?php

namespace Laramie\Lib;

use Laramie\Services\LaramieDataService;

class FieldSpec extends JsonBackedObject
{
    /* Attributes that pertain to all field types */
    public function getDefault() { return $this->get('default'); }
    public function getExtra() { return $this->get('extra'); }
    public function getHelpText() { return $this->get('helpText'); }
    public function getId() { return $this->get('id'); }
    public function getLabel() { return $this->get('label'); }
    public function getLabelPlural() { return $this->get('labelPlural'); }
    public function getShowWhen() { return $this->get('showWhen'); }
    public function getSortBy() { return $this->get('sortBy'); }
    public function getType() { return $this->get('type'); }
    public function getValidation() { return $this->get('validation'); }
    public function getWeight() { return $this->get('weight'); }
    public function isEditable() { return $this->get('isEditable'); }
    public function isListByDefault() { return $this->get('isListByDefault'); }
    public function isListable() { return $this->get('isListable'); }
    public function isMetaField() { return $this->get('isMetaField'); }
    public function isRequired() { return $this->get('isRequired'); }
    public function isSearchable() { return $this->get('isSearchable'); }
    public function isSortable() { return $this->get('isSortable'); }

    /* Computed field attributes */
    public function getDataType() { return $this->get('dataType'); }
    public function getSql() { return $this->get('sql'); }
    public function isDeferred() { return $this->get('isDeferred'); }

    /* File field attributes */
    public function canChooseFromLibrary() { return $this->get('canChooseFromLibrary'); }
    public function getDisk() { return $this->get('disk'); }
    public function getSubtype() { return $this->get('subtype'); }
    public function isPublic() { return $this->get('isPublic'); }
    public function isTypeSpecific() { return $this->get('isTypeSpecific'); }

    /* HTML field attributes */
    public function getHtml() { return $this->get('html'); }

    /* Hidden field attributes */
    public function isVisibleOnEdit() { return $this->get('isVisibleOnEdit'); }

    /* Currency / Number / Range field attributes */
    public function getMax() { return $this->get('max'); }
    public function getMin() { return $this->get('min'); }
    public function getSign() { return $this->get('sign'); }
    public function getStep() { return $this->get('step'); }
    public function isIntegerOnly() { return $this->get('isIntegerOnly'); }

    /* Radio field attributes */
    public function getOptions() { return $this->get('options'); }

    /* Reference field attributes */
    public function getRelatedModel() { return $this->get('relatedModel'); }
    // getSubtype defined in file field section

    /* Select field attributes */
    public function asRadio() { return $this->get('asRadio'); }
    public function isMultiple() { return $this->get('isMultiple'); }
    public function isSelect2() { return $this->get('isSelect2'); }
    // getOptions defined in radio section

    /* Aggregate field attributes */
    public function asTab() { return $this->get('asTab'); }
    public function getFields() { return $this->get('fields'); }
    public function getMaxItems() { return $this->get('maxItems'); }
    public function getMinItems() { return $this->get('minItems'); }
    public function isHideLabel() { return $this->get('isHideLabel'); }
    public function isRepeatable() { return $this->get('isRepeatable'); }
    public function isUnwrap() { return $this->get('isUnwrap'); }
}
