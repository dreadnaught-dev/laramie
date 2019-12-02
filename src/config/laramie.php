<?php

return [
    /*
    |--------------------------------------------------------------------------
    | site_name
    |--------------------------------------------------------------------------
    |
    | Can be overridden to change what text is shown in the top-left of the admin.
    |
    */
    'site_name' => 'Laramie',

    /*
    |--------------------------------------------------------------------------
    | dashboard_override
    |--------------------------------------------------------------------------
    |
    | Set this to 'vanilla' for the default dashboard, or alternatively, to the
    | name of a model whose list page should serve as the admin dashboard.
    |
    */
    //'dashboard_override' => 'vanilla',

    /*
    |--------------------------------------------------------------------------
    | username
    |--------------------------------------------------------------------------
    |
    | This is the user field used to link a user to a laramie admin user.
    | Similar to defining a username method on a login controller to override,
    | you may choose a different user attribute here (must be unique for users).
    |
    */
    'username' => 'email',

    /*
    |--------------------------------------------------------------------------
    | Model Path
    |--------------------------------------------------------------------------
    |
    | The path to your main Laramie model schema (can be an array, if multiple
    | schemas need to be processed).
    |
    */
    'model_path' => [base_path('models/kitchen-sink.json')],

    /*
    |--------------------------------------------------------------------------
    | Storage Disk
    |--------------------------------------------------------------------------
    |
    | Define which storage disk Laramie should save files to.
    |
    */
    'storage_disk' => 'local',

    /*
    |--------------------------------------------------------------------------
    | Disable Meta (tags/comments)
    |--------------------------------------------------------------------------
    |
    | By default, meta is enabled in Laramie. Set this to true to disable meta
    | admin-wide (may also disable meta on a model-by-model basis by setting
    | `"disableMeta": true` on a model.
    |
    */
    'disable_meta' => false,

    /*
    |--------------------------------------------------------------------------
    | Admin URL
    |--------------------------------------------------------------------------
    |
    | Define the base route that should serve the admin, by default, it's /admin.
    |
    */
    'admin_url' => '/admin',

    /*
    |--------------------------------------------------------------------------
    | Enable dual auth
    |--------------------------------------------------------------------------
    |
    | Set whether or not dual authentication is enabled by default.
    |
    */
    'enable_mfa' => false,

    /*
    |--------------------------------------------------------------------------
    | Max number of records to export to CSV at one time
    |--------------------------------------------------------------------------
    |
    | Exporting records to CSV can be a processor intensive function. Set limit higher if needed
    |
    */
    'max_csv_records' => 5000,

    /*
    |--------------------------------------------------------------------------
    | Max number of revisions to show on edit screen by default
    |--------------------------------------------------------------------------
    */
    'visible_revisions' => 5,

    /*
    |--------------------------------------------------------------------------
    | Extensions allowed to be uploaded in image field
    |--------------------------------------------------------------------------
    */
    'allowed_image_types' => ['bmp', 'gif', 'jpg', 'jpeg', 'png', 'svg'],

    /*
    |--------------------------------------------------------------------------
    | Default visibility of uploaded files
    |--------------------------------------------------------------------------
    */
    'files_are_public_by_default' => true,

    /*
    |--------------------------------------------------------------------------
    | Default bulk actions
    |--------------------------------------------------------------------------
    */
    'default_bulk_actions' => ['Delete', 'Duplicate', 'Export (to CSV)'],

    /*
    |--------------------------------------------------------------------------
    | Timezone - when searching by date, interpret dates to be in this timezone
    |--------------------------------------------------------------------------
    */
    'timezone' => 'America/Chicago',

    /*
    |--------------------------------------------------------------------------
    | Enable Local API -- allow logged in users to hit the API (as well as
    | users authenticated via basic auth). Will add some overhead to requests
    | as setting to true will require loading sessions.
    |--------------------------------------------------------------------------
    */
    'enable_local_api' => false,

    /*
    |--------------------------------------------------------------------------
    | Suppress most events (pre-save, post-save, shape-list-query, etc).
    | Useful when you want full and explicit control over modifying a
    | LaramieModel.
    | You may set at runtime by `config(['laramie.suppress_events' => true]);`
    |--------------------------------------------------------------------------
    */
    'suppress_events' => false,

    /*
    |--------------------------------------------------------------------------
    | When saving, you may apply a prefix to the file to help organize things.
    | By default, files will be placed in a directory with the id of the user saving.
    |--------------------------------------------------------------------------
    */
    'file_prefix' => '{*user.id*}/',
];
