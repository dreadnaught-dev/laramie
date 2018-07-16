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
    'dashboard_override' => 'vanilla',

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
    'enable_dual_auth' => false,

    'duo' => [
        'integrationKey' => env('DUO_INTEGRATION_KEY'),
        'secretKey' => env('DUO_SECRET_KEY'),
        'apiHostname' => env('DUO_API_HOSTNAME'),
    ],

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
];
