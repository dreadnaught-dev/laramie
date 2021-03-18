<?php

namespace Laramie\Hooks;

use Illuminate\Foundation\Auth\User;

use Laramie\Lib\LaramieModel;
use Laramie\Lib\FileInfo;

/*
 * Augment file info _before_ it's saved (alter its path, etc).
 */
class ModifyFileInfoPreSave
{
    public User $user;
    public $fileInfo;

    public function __construct(User $user, FileInfo $fileInfo)
    {
        $this->user = $user;
        $this->fileInfo = $fileInfo;
    }
}
