<?php

namespace Laramie\Events;

use Laramie\Lib\LaramieModel;
use Laramie\Lib\FileInfo;

class ModifyFileInfoPreSave
{
    public $user;
    public $fileInfo;

    /**
     * Create a PostDelete event instance. Listeners _may_ be asynchronous.
     *
     * @param stdClass                 $model JSON-decoded model definition (from laramie-models.json, etc).
     * @param Laramie\Lib\LaramieModel $item  the db item that was edited
     * @param Laramie\Lib\LaramieModel $user  laramie's version of the logged in user
     */
    public function __construct(LaramieModel $user, FileInfo $fileInfo)
    {
        $this->user = $user;
        $this->fileInfo = $fileInfo;
    }
}
