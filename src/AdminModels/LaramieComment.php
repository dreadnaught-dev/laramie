<?php

namespace Laramie\AdminModels;

use Carbon\Carbon;
use DB;

use Laramie\Lib\LaramieModel;
use Laramie\Lib\LaramieHelpers;

class LaramieComment extends LaramieModel
{
    static $userHash = [];

    public static function createFromText($id, $rawText)
    {
        return self::create([
            'relatedItemId' => $id,
            'comment' => LaramieHelpers::getLaramieMarkdownObjectFromRawText($rawText),
        ]);
    }

    public function getAjaxViewModel()
    {
        $commentAuthor = $this->getAuthor();

        $userFirstInitial = strtoupper(substr(data_get($commentAuthor, 'handle', '?'), 0, 1));

        return (object) [
            'id' => $this->id,
            'userFirstInitial' => $userFirstInitial,
            'user' => data_get($commentAuthor, 'handle', 'Unknown'),
            'color' => LaramieHelpers::getOrdinalColor(ord(strtolower($userFirstInitial))),
            'lastModified' => Carbon::parse($this->updated_at)->diffForHumans(),
            'comment' => $this->comment,
        ];

        return $comment;
    }

    private function getAuthor()
    {
        if (!array_key_exists($this->user_id, static::$userHash)) {
            static::$userHash[$this->user_id] = DB::table('users')->where('id', $this->user_id)->addSelect(DB::raw(config('laramie.username') . ' as handle'))->first();
        }

        return static::$userHash[$this->user_id];
    }
}