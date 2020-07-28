<?php

namespace Laramie;

class Globals
{
    /** @var string UUID of the super admin role */
    const SuperAdminRoleId = 'b4eeef88-2b98-11e7-a949-56df5f2b76ee';

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
}
