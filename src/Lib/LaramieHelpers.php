<?php

declare(strict_types=1);

namespace Laramie\Lib;

use Carbon\Carbon;
use Illuminate\Http\File;
use Intervention\Image\ImageManager;
use Laramie\Globals;
use Ramsey\Uuid\Uuid;
use Storage;

class LaramieHelpers
{
    /**
     * Return the current URL modified with QS parameters as taken from `$qsParts`.
     *
     * @param string[] $qsParts Map of key->value pairs to augment the current URL with
     *
     * @return string Augmented URL
     */
    public static function getCurrentUrlWithModifiedQS(array $qsParts)
    {
        $qs = request()->all();
        $curSort = data_get($qs, 'sort', data_get($qsParts, 'sort'));
        $curSortDirection = data_get($qs, 'sort-direction', data_get($qsParts, 'sort-direction'));
        foreach ($qsParts as $key => $value) {
            $qs[$key] = $value;
        }

        $qs = collect($qs)
            ->filter(function ($item) { return $item && gettype($item) != 'array'; }) // exclude `bulk-action-ids`
            ->map(function ($value, $key) {
                return "$key=$value";
            })
            ->values()
            ->all();

        return url()->current().(count($qs) ? '?'.implode('&', $qs) : '');
    }

    /**
     * Format a value for display on the list page and csv export.
     *
     * @param stdClass $field                   The JSON field definition
     * @param mixed    $value                   The value for the field as saved in the db
     * @param bool     $isShowUnsupporteMessage If a field type isn't matched, determine whether or nothing or an an error message
     *
     * @return string Formatted value
     */
    public static function formatListValue(FieldSpec $field, $value, $isShowUnsupporteMessage = true)
    {
        $dataType = $field->getDataType();

        // Add the ability for a listener to override _how_ a type of data is
        // formatted for display. Also allows for user-defined field types to
        // be formatted on list pages and csv exports.
        $formattedValueFromHook = \Laramie\Hook::fire(new \Laramie\Hooks\FormatDisplayValue($dataType, $value));
        if (isset($formattedValueFromHook)) {
            return $formattedValueFromHook;
        }

        switch ($dataType) {
            case 'text':
            case 'hidden':
            case 'phone':
            case 'email':
            case 'number':
            case 'currency':
            case 'currency':
            case 'month':
            case 'tel':
            case 'url':
            case 'week':
            case 'time':
            case 'checkbox':
            case 'computed':
                if ($listTemplate = $field->getListTemplate()) {
                    return str_replace('{{value}}', (string) $value, $listTemplate);
                }

                return $value;
            case 'radio':
            case 'select':
                $options = collect($field->getOptions());
                $value = is_array($value) ? $value : [$value];
                $returnValue = collect($value)->map(function ($item, $key) use ($options) {
                    return data_get($options->firstWhere('value', $item), 'text', $item);
                });

                return implode(', ', $returnValue->toArray());
            case 'color':
                return sprintf('<span style="color:%s">%s</span>', $value, $value);
            case 'range':
                return sprintf('%s/%s', $value, ($field->getMax() ?: 100));
            case 'password':
                return $value && data_get($value, 'encryptedValue') ? '********' : '';
            case 'reference':
                $tmp = !$field->hasMany ? [$value] : $value;

                return collect($tmp)->map(function ($e) {
                    return data_get($e, '_alias');
                })->implode(', ');
            case 'boolean':
                return $value ? 'Yes' : 'No';
            case 'date':
            case 'dbtimestamp':
                $dateFormat = config('laramie.date_presentation_format') ?: 'Y-m-d'; // Carbon's default if none provided

                return $value ? Carbon::parse($value)->format($dateFormat) : '';
            case 'datetime':
            case 'datetime-local':
                $dateFormat = config('laramie.datetime_presentation_format') ?: 'Y-m-d H:i:s'; // Carbon's default if none provided

                return $value ? Carbon::parse($value)->format($dateFormat) : '';
            case 'dateDiff':
                if ($value) {
                    return Carbon::parse($value)->diffForHumans();
                }
                // no break
            case 'file':
            case 'image':
                return data_get($value, 'name');
            case 'textarea':
            case 'wysiwyg':
                if ($isShowUnsupporteMessage) {
                    return substr(strip_tags($value), 0, 100).'...';
                }

                return $value;
            case 'markdown':
                $markdown = data_get($value, 'markdown');
                if ($isShowUnsupporteMessage) {
                    return substr($markdown, 0, 100).'...';
                }

                return $markdown;
            case 'timestamp':
                $timestamp = data_get($value, 'timestamp');
                $timezone = data_get($value, 'timezone');
                if ($timestamp) {
                    return Carbon::createFromTimestamp($timestamp, $timezone)->toDateTimeString();
                }

                return '';
            case 'html':
                return $value ?: $field->getHtml();
            default:
                if ($isShowUnsupporteMessage) {
                    return sprintf('%s field type not supported (field: %s)', $field->getType(), $field->getLabel());
                }

                return $value;
        }
    }

    public static function getOrdinalColor($charValue)
    {
        // Adapted from: https://gist.github.com/bendc/76c48ce53299e6078a76
        $h = ($charValue * 9811) % 360;
        $s = (($charValue * 72883) % 56) + 42;
        $l = (($charValue * 104729) % 50) + 40;

        return "hsl($h,$s%,$l%)";
    }

    /**
     * Take in a markdown string and convert it to HTML.
     *
     * @param string $markdown
     *
     * @return string HTML-version of the markdown
     */
    public static function markdownToHtml($markdown)
    {
        $parsedown = new \Parsedown();
        $parsedown->setUrlsLinked(false);

        return $parsedown->text($markdown);
    }

    public static function getInterventionImageDriver()
    {
        return extension_loaded('imagick') ? 'imagick' : 'gd';
    }

    /**
     * Get the path to a Storage::disk file _on_ the filesystem.
     *
     * Some operations need a local file to operate on. This method pulls
     * s3/rackspace files down first so they can be operated on (e.g.,
     * `File($path)`).
     *
     * @param LaramieModel $item
     * @param string?      $postfix Used for icon in the admin. Inserts a postfix _before_ the file extension.
     *
     * @return string path to local file
     */
    public static function getLocalFilePath($item, $postfix = null)
    {
        if ($item === null) {
            return null;
        }
        $storageDisk = config('laramie.storage_disk');
        $storageDriver = config('filesystems.disks.'.$storageDisk.'.driver');

        $filePath = self::applyPathPostfix($item->fullPath, $postfix);

        // For storage disks with a non-local driver, we need to get the remote file onto
        // the local filesystem so we can access it in the next part. Not sure if there's
        // a way around that, but it seems a little redundant.
        if ($storageDriver != 'local') {
            $filePath = tempnam(sys_get_temp_dir(), 'LAR');
            $handle = fopen($filePath, 'w');
            fwrite($handle, Storage::disk($storageDisk)->get(self::applyPathPostfix($item->path, $postfix)));
            fclose($handle);
        }

        return $filePath;
    }

    public static function applyPathPostfix($path, $postfix)
    {
        return $postfix ? preg_replace('/\.([^\.]+)$/', $postfix.'.$1', $path) : $path;
    }

    public static function getTimezones()
    {
        $timezones = collect([]);
        $now = new \DateTime('now', new \DateTimeZone('UTC'));

        foreach (\DateTimeZone::listIdentifiers() as $timezone) {
            $now->setTimezone(new \DateTimeZone($timezone));
            $offset = $now->getOffset();
            $prettyTimezone = self::formatTimezoneName($timezone);
            $sort = ($offset < 0 ? '-' : '').str_pad(($offset < 0 ? 999999999 + $offset : $offset).'', 12, '0', STR_PAD_LEFT).$prettyTimezone;
            $timezones->push((object) ['offset' => $offset, 'prettyOffset' => self::formatGmtOffset($offset), 'timezone' => $timezone, 'prettyTimezone' => $prettyTimezone, 'sort' => $sort]);
        }

        return $timezones->sortBy('sort');
    }

    public static function formatGmtOffset($offset, $showGmt = true)
    {
        $hours = intval($offset / 3600);
        $minutes = abs(intval($offset % 3600 / 60));

        return ($showGmt ? 'GMT' : '').($offset ? sprintf('%+03d:%02d', $hours, $minutes) : '');
    }

    public static function formatTimezoneName($name)
    {
        $name = str_replace('_', ' ', $name);
        $name = str_replace('St ', 'St. ', $name);

        return $name;
    }

    public static function getLaramieTimestampObjectFromCarbonDate(Carbon $c)
    {
        $cDate = $c->format('Y-m-d');
        $cTime = $c->format('H:i:s');

        return (object) ['date' => $cDate, 'time' => $cTime, 'timezone' => $c->getTimezone()->getName(), 'timestamp' => $c->timestamp];
    }

    public static function getLaramiePasswordObjectFromPasswordText($plaintextPassword)
    {
        return (object) ['encryptedValue' => $plaintextPassword ? \Hash::make($plaintextPassword) : null];
    }

    public static function getLaramieMarkdownObjectFromRawText($rawText)
    {
        return (object) ['markdown' => $rawText, 'html' => self::markdownToHtml($rawText)];
    }

    // Create thumbnails / etc. Normally, don'throw exception if this step can't complete. Admin thumbnails should be seen as optional.
    public static function postProcessLaramieUpload($upload, $swallowErrors = true)
    {
        // @optimize -- move thumb gen to postsave
        // If the upload is an image, create thumbnails (for use by the admin)
        $storageDisk = config('laramie.storage_disk');
        if ($upload->extension && preg_match('/^('.implode('|', Globals::SUPPORTED_RASTER_IMAGE_TYPES).')$/i', $upload->extension)) { // only try to take thumbnails of a subset of allowed image types:
            $filePath = static::getLocalFilePath($upload);
            $manager = new ImageManager(['driver' => static::getInterventionImageDriver()]);
            $thumbWidths = [50]; // Currently only make one small thumbnail
            foreach ($thumbWidths as $width) {
                $tmpThumbnailPath = tempnam(sys_get_temp_dir(), 'LAR');
                try {
                    $image = $manager->make($filePath);
                    $image->fit($width);
                    $image->save($tmpThumbnailPath);
                    // Save to Laramie's configured storage disk:
                    $thumbnail = new File($tmpThumbnailPath);
                    Storage::disk($storageDisk)->putFileAs('', $thumbnail, static::applyPathPostfix($upload->path, '_'.$width), (data_get($upload, 'isPublic') ? 'public' : 'private'));
                } catch (\Exception $e) {
                    if (!$swallowErrors) {
                        throw $e;
                    }
                } finally {
                    unlink($tmpThumbnailPath);
                }
            }
        }
        // @optimize -- can we add a temp attribute that lets us know if we need to do this
        // or not? We're doing it every time because it needs to be done in the case of
        // new upload or a scaled / cropped image. Not in the case of a name being
        // updated. But we have no way to tell right now _how_ an upload has been updated.
        // Copy to public if specified
        if ($upload->isPublic) {
            $filePath = static::getLocalFilePath($upload);

            // `$filePath` is a pointer to a file on the local filesystem that `File` can load
            $file = new File($filePath);
            $tmp = Storage::disk('public')->putFileAs('', $file, $upload->path, 'public');
            $upload->publicPath = Storage::disk('public')->url($tmp);
        } else { // delete file if switched from public to private
            try {
                Storage::disk('public')->delete($upload->path);
            } catch (Exception $e) { /* don't error if the public version of the file can't be deleted -- may have been manually deleted */
                dd($e->getMessage());
            }
        }
    }

    public static function isValidUuid(?string $uuid): bool
    {
        if ($uuid) {
            return Uuid::isValid($uuid);
        }

        return false;
    }

    public static function extractFiltersFromData($qs)
    {
        $filterRegex = '/^filter_(?<filterIndex>[^_]+)_field$/';

        return collect($qs)
            ->filter(function ($e, $key) use ($filterRegex) {
                return preg_match($filterRegex, $key) && $e;
            })
            ->map(function ($e, $key) use ($filterRegex, $qs) {
                preg_match($filterRegex, $key, $matches);

                return (object) [
                    'key' => $matches['filterIndex'],
                    'field' => data_get($qs, sprintf('filter_%s_field', $matches['filterIndex'])),
                    'operation' => data_get($qs, sprintf('filter_%s_operation', $matches['filterIndex']), 'is equal to'), // short-hand filter; default to equality check if not specified
                    'value' => data_get($qs, sprintf('filter_%s_value', $matches['filterIndex'])),
                ];
            })
            ->values()
            ->all();
    }
}
