<?php

namespace Laramie\AdminModels;

use Carbon\Carbon;

use Laramie\Lib\LaramieModel;
use Laramie\Lib\LaramieHelpers;

class LaramieAlert extends LaramieModel
{
    public function getAuthorName()
    {
        return data_get($this, 'authorName', '??');
    }

    public function getColor()
    {
        return LaramieHelpers::getOrdinalColor(ord(strtolower($this->getUserInitial())));
    }

    public function getUserInitial()
    {
        return strtoupper(substr(data_get($this, 'authorName', '?'), 0, 1));
    }

    public function getHumanReadableCreatedDate()
    {
        return Carbon::parse($this->updated_at)->diffForHumans();
    }

    public function getMessage()
    {
        return data_get($this, 'message.html');
    }
}
