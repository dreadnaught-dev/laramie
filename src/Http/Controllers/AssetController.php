<?php

namespace Laramie\Http\Controllers;

use Illuminate\Http\File;
use Illuminate\Http\Request;
use Intervention\Image\ImageManager;
use Storage;
use Exception;

use Laramie\Hook;
use Laramie\Hook\PreSave;
use Laramie\Hook\PostSave;
use Laramie\Events\ItemSaved;
use Laramie\Globals;
use Laramie\Lib\LaramieHelpers;
use Laramie\Services\LaramieDataService;

/**
 * The AssetController serves files and facilitates image manipulation.
 */
class AssetController extends Controller
{
    protected $dataService;

    /**
     * Create a new AssetController.
     *
     * @param LaramieDataService $dataService Inject the service that talks to the db
     *
     * @return AssetController
     */
    public function __construct(LaramieDataService $dataService)
    {
        $this->dataService = $dataService;
    }

    /**
     * Return a view where one can crop / rotate / resize / etc images.
     *
     * @param LaramieDataService $dataService Inject the service that talks to the db
     * @param string             $imageKey    The asset's key
     *
     * @return \Illuminate\Http\Response
     */
    public function showCropper(Request $request, $imageKey)
    {
        $item = $this->dataService->findById('laramieUpload', $imageKey);

        return view('laramie::cropper', [
            'item' => $item,
            'imageKey' => $imageKey,
        ]);
    }

    /**
     * Perform image manipulation on an image (more than just cropping).
     *
     * @param LaramieDataService $dataService Inject the service that talks to the db
     * @param string             $imageKey    The asset's key
     *
     * @return \Illuminate\Http\Response
     */
    public function cropImage(Request $request, $imageKey)
    {
        $item = $this->dataService->findById('laramieUpload', $imageKey);
        $item->alt = strip_tags($request->get('alt'));
        $this->dataService->save('laramieUpload', $item);

        $image = $this->getInterventionImage($imageKey);

        if (data_get($request, 'scaleX') == '-1') {
            $image->flip('h');
        }

        if (data_get($request, 'scaleY') == '-1') {
            $image->flip('v');
        }

        if (data_get($request, 'rotate') != '0') {
            $image->rotate(data_get($request, 'rotate'));
        }

        if (floor(data_get($request, 'width')) &&
            floor(data_get($request, 'height'))
        ) {
            $image->crop(
                floor(data_get($request, 'width')),
                floor(data_get($request, 'height')),
                floor(data_get($request, 'x')),
                floor(data_get($request, 'y'))
            );

            // Resize the image if it has been zoomed (although this may be removed -- obvious behavior?)
            if (data_get($request, 'zoom') !== '1') {
                $newWidth = floor(data_get($request, 'width') * data_get($request, 'zoom'));
                $image->resize($newWidth, null, function ($constraint) {
                    $constraint->aspectRatio();
                });
            }
        }

        $uploadModel = $this->dataService->getModelByKey('laramieUpload');
        $item = $this->dataService->findById($uploadModel, $imageKey);

        // Leverage intervention image to save, then copy to Laramie's storage disk:
        $tmp = tempnam(sys_get_temp_dir(), 'LAR');
        $image->save($tmp);
        $file = new File($tmp);
        Storage::disk(config('laramie.storage_disk'))->putFileAs('', $file, $item->path);

        Hook::fire(new PreSave($uploadModel, $item, $this->dataService->getUser()));
        Hook::fire(new PostSave($uploadModel, $item, $this->dataService->getUser()));
        event(new ItemSaved($uploadModel, $item, $this->dataService->getUser()));

        return redirect()
            ->route('laramie::cropper', ['imageKey' => $imageKey])
            ->with('alert', (object) ['title' => 'Success!', 'alert' => 'The image was successfully updated.']);
    }

    /**
     * Return an InterventionImage (image manip wrapper).
     *
     * @param string $imageKey The asset's key
     *
     * @return Intervention\Image\ImageManager
     */
    private function getInterventionImage($imageKey)
    {
        $imageKeyParts = $this->getImageKeyAndPostfix($imageKey);
        $fileInfo = $this->dataService->getFileInfo($imageKeyParts->key);

        // Don't try to get an image for a non-image type file
        if (!in_array(data_get($fileInfo, 'extension'), Globals::SUPPORTED_RASTER_IMAGE_TYPES)) {
            throw new Exception('File type not supported');
        }

        $filePath = LaramieHelpers::getLocalFilePath($fileInfo, $imageKeyParts->postfix);

        try {
            $dims = getimagesize($filePath);

            $width = data_get($dims, 0, 0);
            $height = data_get($dims, 1, 0);
            if (!($width && $height)
                || ($width > Globals::MAX_IMAGE_DIMENSION || $height > Globals::MAX_IMAGE_DIMENSION)
            ) {
                throw new Exception('Image size error');
            }

            $manager = new ImageManager(['driver' => LaramieHelpers::getInterventionImageDriver()]);

            $image = $manager->make($filePath);
        } catch (Exception $e) {
            throw $e;
        }
        finally {
            unlink($filePath);
        }

        return $image;
    }

    private function getImageKeyAndPostfix($compositeKey)
    {
        preg_match('/(?<postfix>_.*$)/', $compositeKey, $matches);
        $key = $compositeKey;
        $postfix = data_get($matches, 'postfix', '');
        if ($postfix) {
            $key = preg_replace('/_.*$/', '', $compositeKey);
        }

        return (object) ['key' => $key, 'postfix' => $postfix];
    }

    /**
     * Return an image as the HTTP response. If the image isn't found, a fallback image will be returned.
     *
     * @param string $imageKey The asset's key
     *
     * @return \Illuminate\Http\Response
     */
    public function showIcon(Request $request, $imageKey)
    {
        try {
            $image = $this->getInterventionImage($imageKey);

            return $image->response();
        } catch (\Exception $e) {
            $imageKeyParts = $this->getImageKeyAndPostfix($imageKey);
            $fileInfo = $this->dataService->getFileInfo($imageKeyParts->key);
            $extension = data_get($fileInfo, 'extension');
            $filePath = public_path('laramie/admin/icons/file.png');
            if (in_array($extension, Globals::VALID_ICON_TYPES)) {
                $tmpFilePath = public_path('laramie/admin/icons/'.$extension.'.png');
                if (file_exists($filePath)) {
                    $filePath = $tmpFilePath;
                }
            }
            $manager = new ImageManager(['driver' => LaramieHelpers::getInterventionImageDriver()]);

            return $manager->make($filePath)->response();
        }
    }

    /**
     * Return an image as the HTTP response.
     *
     * @param string $imageKey The asset's key
     *
     * @return \Illuminate\Http\Response
     */
    public function showImage(Request $request, $imageKey)
    {
        try {
            $image = $this->getInterventionImage($imageKey);

            return $image->response();
        } catch (\Exception $e) {
            abort(404);
        }
    }

    /**
     * Return a file as the HTTP response.
     *
     * @param string $key The asset's key
     *
     * @return \Illuminate\Http\Response
     */
    public function downloadFile(Request $request, $assetKey)
    {
        try {
            $fileInfo = $this->dataService->getFileInfo($assetKey);

            $headers = [];

            if ($request->get('disposition') === 'inline') {
                $headers['Content-Disposition'] = 'inline; filename="'.$fileInfo->name.'"';
            }

            return \Storage::disk(config('laramie.storage_disk'))->download($fileInfo->path, $fileInfo->name, $headers);
        } catch (\Exception $e) {
            abort(404);
        }
    }
}
