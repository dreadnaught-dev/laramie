<?php

declare(strict_types=1);

namespace Laramie\AdminModels;

use Carbon\Carbon;
use DB;
use Laramie\Lib\LaramieHelpers;
use Laramie\Lib\LaramieModel;

class LaramieAlert extends LaramieModel
{
    public static $userHash = [];

    public function getAuthorName()
    {
        return data_get($this->getAuthor(), 'handle', '??');
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

    private function getAuthor()
    {
        if (!array_key_exists($this->user_id, static::$userHash)) {
            static::$userHash[$this->user_id] = DB::table('users')->where('id', $this->user_id)->addSelect(DB::raw(config('laramie.username').' as handle'))->first();
        }

        return static::$userHash[$this->user_id];
    }
}
