<?php

namespace Laramie\Lib;

class ModelSpec
{
    private object $data;

    public function __construct($jsonObject)
    {
        $this->data = $jsonObject;
    }

    public function toData()
    {
        return $this->data;
    }

    private function get($key, $fallback = null)
    {
        return data_get($this->data, $key, $fallback);
    }

    public function getType() : string { return $this->get('_type'); }

    public function getAlias() : string { return $this->get('alias'); }

    public function getName() : string { return $this->get('name'); }

    public function getNamePlural() : string { return $this->get('namePlural'); }

    public function getMainTabLabel() : string { return $this->get('mainTabLabel', 'Main'); }

    public function getDefaultSort() : string { return $this->get('defaultSort'); }

    public function getDefaultSortDirection() : string { return $this->get('defaultSortDirection'); }

    public function getQuickSearch() : array { return $this->get('quickSearch'); }

    public function isListable() : bool { return $this->get('isListable', true); }

    public function isEditable() : bool { return $this->get('isEditable', true); }

    public function isDeletable() : bool { return $this->get('isDeletable', true) === false; }

    public function isSingular() : bool { return $this->get('isSingular', false); }

    public function isSystemModel() : bool { return $this->get('isSystemModel', false); }

    public function isDisableMeta() : bool { return $this->get('isDisableMeta', false); }

    public function isDisableRevisions() : bool { return $this->get('isDisableRevisions', false) === true; }

    public function getListView() : string { return $this->get('listView', 'laramie::list-page'); }

    public function getListCss() : array { return $this->get('listCss', []); }

    public function getListJs() : array { return $this->get('listJs', []); }

    public function getEditView() : string { return $this->get('editView', 'laramie::edit-page'); }

    public function getEditCss() : array { return $this->get('editCss', []); }

    public function getEditJs() : array { return $this->get('editJs', []); }

    public function getRefs() : array { return collect($this->get('refs', []))->map(function($item) { return new RefSpec($item); })->toArray(); }

    // Fields
    public function getFields() { return $this->get('fields', (object) []); }
    public function getField($fieldName) { return $this->get('fields.' . $fieldName); }
    public function addField(string $key, object $fieldInfo) { $this->getFields()->{$key} = ModelLoader::processField($key, $fieldInfo); }
    // End fields

    public function getAddNewText() : string { return $this->get('addNewText', 'Add New'); } /* @TODO preston -- update to use laravel translation */

    public function getFactory() : string { return $this->get('factory', LaramieModel::class); }

    public function getBulkActions() : array { return $this->get('bulkActions', config('laramie.default_bulk_actions')); }

    public function getJsonValidator() { return $this->get('_jsonValidator'); }
    public function setJsonValidator($jsonValidationSchema) { return $this->data->{'_jsonValidator'} = $jsonValidationSchema; }

}
