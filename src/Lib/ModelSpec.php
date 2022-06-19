<?php

declare(strict_types=1);

namespace Laramie\Lib;

class ModelSpec extends JsonBackedObject implements FieldContainer
{
    public function getAddNewText(): string
    {
        return $this->get('addNewText', 'Add New');
    } /* @TODO preston -- update to use laravel translation */

    public function getAlias(): string
    {
        return $this->get('alias');
    }

    public function getBulkActions(): array
    {
        return $this->get('bulkActions', config('laramie.default_bulk_actions'));
    }

    public function getDefaultSort(): string
    {
        return $this->get('defaultSort');
    }

    public function getDefaultSortDirection(): string
    {
        return $this->get('defaultSortDirection');
    }

    public function getEditCss(): array
    {
        return $this->get('editCss', []);
    }

    public function getEditJs(): array
    {
        return $this->get('editJs', []);
    }

    public function getEditView(): string
    {
        return $this->get('editView', 'laramie::edit-page');
    }

    public function getFactory(): string
    {
        return $this->get('factory', LaramieModel::class);
    }

    public function getJsonValidator()
    {
        return $this->get('_jsonValidator');
    }

    public function getListCss(): array
    {
        return $this->get('listCss', []);
    }

    public function getListJs(): array
    {
        return $this->get('listJs', []);
    }

    public function getListView(): string
    {
        return $this->get('listView', 'laramie::list-page');
    }

    public function getMainTabLabel(): string
    {
        return $this->get('mainTabLabel', 'Main');
    }

    public function getName(): string
    {
        return $this->get('name');
    }

    public function getNamePlural(): string
    {
        return $this->get('namePlural');
    }

    public function getQuickSearch(): array
    {
        return $this->get('quickSearch');
    }

    public function getRefs(): array
    {
        return collect($this->get('refs', []))->map(function ($item) { return new RefSpec($item); })->toArray();
    }

    public function getType(): string
    {
        return $this->get('_type');
    }

    public function isDeletable(): bool
    {
        return $this->get('isDeletable') !== false;
    } // default to true

    public function isDisableMeta(): bool
    {
        return $this->get('isDisableMeta') === true;
    } // default to false

    public function isDisableRevisions(): bool
    {
        return $this->get('isDisableRevisions') === true;
    } // default to false

    public function isEditable(): bool
    {
        return $this->get('isEditable') !== false;
    } // default to true

    public function isListable(): bool
    {
        return $this->get('isListable') !== false;
    } // default to true

    public function isSingular(): bool
    {
        return $this->get('isSingular') === true;
    } // default to false

    public function isSystemModel(): bool
    {
        return $this->get('isSystemModel') === true;
    } // default to false

    public function setJsonValidator($jsonValidationSchema)
    {
        return $this->data->{'_jsonValidator'} = $jsonValidationSchema;
    }

    /* Field-related functions */
    public function addField(string $key, object $fieldInfo)
    {
        $this->getFields()->{$key} = ModelLoader::processField($key, $fieldInfo);
    }

    public function getField($fieldName)
    {
        return $this->get('fields.'.$fieldName);
    }

    public function getFieldSpec($fieldName)
    {
        $f = $this->get('fields.'.$fieldName);

        return $f ? new FieldSpec($this->get('fields.'.$fieldName)) : null;
    }

    public function getFields()
    {
        return $this->get('fields', (object) []);
    }

    public function getFieldsSpecs()
    {
        return collect($this->get('fields', (object) []))->map(function ($item) { return new FieldSpec($item); })->toArray();
    }
}
