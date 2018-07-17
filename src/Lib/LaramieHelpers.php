<?php

namespace Laramie\Lib;

use Illuminate\Http\File;
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
    public function getCurrentUrlWIthModifiedQS(array $qsParts)
    {
        $qs = request()->all();
        $curSort = array_get($qs, 'sort', array_get($qsParts, 'sort'));
        $curSortDirection = array_get($qs, 'sort-direction', array_get($qsParts, 'sort-direction'));
        unset($qs['sort-direction']);
        unset($qsParts['sort-direction']);
        foreach ($qsParts as $key => $value) {
            $qs[$key] = $value;
        }
        $qs = collect($qs)
            ->filter()
            ->map(function ($value, $key) use ($curSort, $curSortDirection) {
                $kvp = "$key=$value";
                if ($key == 'sort') {
                    $sortDirection = 'asc';
                    if ($curSort == $value && $curSortDirection == 'asc') {
                        $sortDirection = 'desc';
                    }
                    $kvp = $kvp.'&sort-direction='.$sortDirection;
                }

                return $kvp;
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
    public static function formatListValue($field, $value, $isShowUnsupporteMessage = true)
    {
        $dataType = object_get($field, 'dataType', $field->type);
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
            case 'radio':
            case 'url':
            case 'week':
            case 'time':
            case 'checkbox':
            case 'computed':
                return $value;
            case 'select':
                if (is_array($value)) {
                    return implode(', ', $value);
                }
                return $value;
            case 'color':
                return sprintf('<span style="color:%s">%s</span>', $value, $value);
            case 'range':
                return sprintf('%s/%s', $value, object_get($field, 'max', 100));
            case 'password':
                return $value && object_get($value, 'encryptedValue') ? '********' : '';
            case 'reference':
                $tmp = $field->subtype == 'single' ? [$value] : $value;

                return collect($tmp)->map(function ($e) {
                    return object_get($e, '_alias');
                })->implode(', ');
            case 'boolean':
                return $value ? 'yes' : 'no';
            case 'date':
                return $value ? \Carbon\Carbon::parse($value)->toDateString() : '';
            case 'datetime':
            case 'datetime-local':
                return $value ? \Carbon\Carbon::parse($value)->toDateTimeString() : '';
            case 'dateDiff':
                if ($value) {
                    return \Carbon\Carbon::parse($value)->diffForHumans();
                }
                // no break
            case 'file':
            case 'image':
                return object_get($value, 'name');
            case 'textarea':
            case 'wysiwyg':
                if ($isShowUnsupporteMessage) {
                    return substr(strip_tags($value), 0, 100).'...';
                }

                return $value;
            case 'markdown':
                $markdown = object_get($value, 'markdown');
                if ($isShowUnsupporteMessage) {
                    return substr($markdown, 0, 100).'...';
                }

                return $markdown;
            case 'timestamp':
                $timestamp = object_get($value, 'timestamp');
                $timezone = object_get($value, 'timezone');
                if ($timestamp) {
                    return \Carbon\Carbon::createFromTimestamp($timestamp, $timezone)->toDateTimeString();
                }

                return '';
            default:
                if ($isShowUnsupporteMessage) {
                    return sprintf('%s field type not supported (field: %s)', $field->type, $field->label);
                }

                return $value;
        }
    }

    public static function transformCommentForDisplay($comment)
    {
        $comment->_userFirstInitial = strtoupper(substr(object_get($comment, '_user', '?'), 0, 1));
        $comment->_user = $comment->_user ?: 'Unknown';
        $comment->_color = self::getOrdinalColor(ord(strtolower($comment->_userFirstInitial)));
        $comment->lastModified = \Carbon\Carbon::parse($comment->updated_at)->diffForHumans();

        return $comment;
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

    public static function getLaramieTimestampObjectFromCarbonDate(\Carbon\Carbon $c)
    {
        $cDate = $c->format('Y-m-d');
        $cTime = $c->format('H:i:s');

        return (object) ['date' => $cDate, 'time' => $cTime, 'timezone' => $c->getTimezone()->getName(), 'timestamp' => $c->timestamp];
    }

    public static function getLaramiePasswordObjectFromPasswordText($plaintextPassword)
    {
        return (object) ['encryptedValue' => $plaintextPassword ? \Hash::make($plaintextPassword) : null];
    }
}
