<?php

namespace Laramie;

class Globals
{
    /** @var string UUID of the admin role */
    const AdminRoleId = '50890cb6-2b9a-11e7-a7e2-be1ff74bace0';

    /** @var Dummy UUID that will likely never be in the DB */
    const DummyId = '31415926-5358-9793-2384-626433832795';

    /** @var int Length of string to use for basic auth username and password fields * */
    const API_TOKEN_LENGTH = 32;

    const VALID_ICON_TYPES = ['ai', 'avi', 'css', 'csv', 'dbf', 'doc', 'dwg', 'exe', 'file', 'fla', 'html',
        'iso', 'jpg', 'json', 'js', 'mp3', 'mp4', 'pdf', 'png', 'ppt', 'psd', 'rtf',
        'svg', 'txt', 'xls', 'xml', 'zip', ];

    const SUPPORTED_RASTER_IMAGE_TYPES = ['jpeg', 'jpg', 'png', 'gif'];

    const MAX_IMAGE_DIMENSION = 5000;

    const LARAMIE_TYPES_CACHE_KEY = 'laramie_non_system_types';

    // TODO -- convert to enum (requires php 8.1)
    const AccessTypes = [
        'create' => 'create',
        'read' => 'read',
        'update' => 'update',
        'delete' => 'delete',
    ];
}
