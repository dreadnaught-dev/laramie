<?php

namespace Laramie\AdminModels;

use Laramie\Lib\LaramieModel;
use Laramie\Lib\LaramieHelpers;

class LaramieComment extends LaramieModel
{
    public function transformCommentForDisplay($comment)
    {
        $comment->_userFirstInitial = strtoupper(substr(data_get($comment, '_user', '?'), 0, 1));
        $comment->_user = $comment->_user ?: 'Unknown';
        $comment->_color = LaramieHelpers::getOrdinalColor(ord(strtolower($comment->_userFirstInitial)));
        $comment->lastModified = Carbon::parse($comment->updated_at, config('laramie.timezone'))->diffForHumans();

        return $comment;
    }
}
