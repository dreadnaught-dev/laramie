<?php

namespace Laramie\Hooks;

use Laramie\Lib\LaramieModel;
use Laramie\Lib\FileInfo;
use Illuminate\Foundation\Auth\User;

/*
 * Augment file info _before_ it's saved (alter its path, etc).
 */
class ModifyFileInfoPreSave
{
    public $user;
    public $fileInfo;

    public function __construct(User $user, FileInfo $fileInfo)
    {
        $this->user = $user;
        $this->fileInfo = $fileInfo;
    }
}
