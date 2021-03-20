<?php

namespace Laramie\Lib;

use Laramie\Services\LaramieDataService;

class FieldSpec
{
    private $data;

    public function __construct($jsonObject)
    {
        $this->data = $jsonObject;
        $this->relatedModel = app(LaramieDataService::class)->getModelByKey($jsonObject->model);
    }

    public function getLabel()
    {
        return data_get($this->data, 'label', $this->relatedModel->getNamePlural());
    }

    public function getType()
    {
        return $this->relatedModel->getType();
    }

    public function getAlias()
    {
        return $this->relatedModel->getAlias();
    }

    public function getField()
    {
        return data_get($this->data, 'throughField');
    }

    public function getQuickSearch()
    {
        return $this->relatedModel->getQuickSearch();
    }
}
