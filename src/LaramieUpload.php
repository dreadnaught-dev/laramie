<?php

declare(strict_types=1);

namespace Laramie;

use Illuminate\Http\File;
use Laramie\Lib\LaramieModel;
use Laramie\Services\LaramieDataService;

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
