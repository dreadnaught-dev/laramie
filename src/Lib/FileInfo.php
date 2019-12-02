<?php

namespace Laramie\Lib;

use Illuminate\Http\UploadedFile;
use Ramsey\Uuid\Uuid;

class FileInfo
{
    public $name = null;
    public $extension = null;
    public $mimeType = null;
    public $source = null;
    public $isPublic = false;
    public $destination = null;

    public function __construct($file, $isPublic, $source, $destination)
    {
        $this->name = $file instanceof UploadedFile
            ? $file->getClientOriginalName()
            : $file->getFilename();

        $this->extension = $file instanceof UploadedFile
            ? $file->getClientOriginalExtension()
            : ($file->getExtension() != '' ? $file->getExtension() : $file->guessExtension());

        $this->mimeType = $file instanceof UploadedFile
           ? $file->getClientMimeType()
           : $file->getMimeType();

        $this->isPublic = $isPublic;

        $this->source = $source;

        $this->destination = preg_replace('/\_fix_extension$/', $this->extension, $destination);
    }
}
