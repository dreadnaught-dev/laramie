<?php

namespace Laramie\Lib;

class ModelSpec extends JsonBackedObject
{
    public function getAddNewText() : string { return $this->get('addNewText', 'Add New'); } /* @TODO preston -- update to use laravel translation */
    public function getAlias() : string { return $this->get('alias'); }
    public function getBulkActions() : array { return $this->get('bulkActions', config('laramie.default_bulk_actions')); }
    public function getDefaultSort() : string { return $this->get('defaultSort'); }
    public function getDefaultSortDirection() : string { return $this->get('defaultSortDirection'); }
    public function getEditCss() : array { return $this->get('editCss', []); }
    public function getEditJs() : array { return $this->get('editJs', []); }
    public function getEditView() : string { return $this->get('editView', 'laramie::edit-page'); }
    public function getFactory() : string { return $this->get('factory', LaramieModel::class); }
    public function getJsonValidator() { return $this->get('_jsonValidator'); }
    public function getListCss() : array { return $this->get('listCss', []); }
    public function getListJs() : array { return $this->get('listJs', []); }
    public function getListView() : string { return $this->get('listView', 'laramie::list-page'); }
    public function getMainTabLabel() : string { return $this->get('mainTabLabel', 'Main'); }
    public function getName() : string { return $this->get('name'); }
    public function getNamePlural() : string { return $this->get('namePlural'); }
    public function getQuickSearch() : array { return $this->get('quickSearch'); }
    public function getRefs() : array { return collect($this->get('refs', []))->map(function($item) { return new RefSpec($item); })->toArray(); }
    public function getType() : string { return $this->get('_type'); }
    public function isDeletable() : bool { return $this->get('isDeletable', true) === false; }
    public function isDisableMeta() : bool { return $this->get('isDisableMeta', false); }
    public function isDisableRevisions() : bool { return $this->get('isDisableRevisions', false) === true; }
    public function isEditable() : bool { return $this->get('isEditable', true); }
    public function isListable() : bool { return $this->get('isListable', true); }
    public function isSingular() : bool { return $this->get('isSingular', false); }
    public function isSystemModel() : bool { return $this->get('isSystemModel', false); }
    public function setJsonValidator($jsonValidationSchema) { return $this->data->{'_jsonValidator'} = $jsonValidationSchema; }

    /* Field-related functions */
    public function addField(string $key, object $fieldInfo) { $this->getFields()->{$key} = ModelLoader::processField($key, $fieldInfo); }
    public function getField($fieldName) { return $this->get('fields.' . $fieldName); }
    public function getFields() { return $this->get('fields', (object) []); }
}
