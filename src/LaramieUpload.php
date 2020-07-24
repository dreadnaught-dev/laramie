<?php

namespace Laramie;

use DB;
use Illuminate\Console\Command;

use Laramie\Globals;
use Laramie\Lib\LaramieHelpers;
use Laramie\Lib\LaramieModel;
use Laramie\Services\LaramieDataService;
use Illuminate\Http\File;

class LaramieUpload extends LaramieModel
{
    public static function createFromFile(File $file, $isPublic = false, $source = null, $destination = null)
    {
        $dataService = app(LaramieDataService::class);

        return static::hydrateWithModel($dataService->saveFile($file, $isPublic, $source, $destination));
    }

    public static function createFromPath($path, $isPublic = false, $source = null, $destination = null)
    {
        // @NOTE: We _may_ want to use php to copy the file locally if it's a URL...

        $file = new File($path);

        return static::createFromFile($file, $isPublic, $source, $destination);
    }
}


