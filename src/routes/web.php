<?php

use Laramie\Http\Middleware\ApiAuthenticate as LaramieApiAuthenticate;
use Laramie\Http\Middleware\Authenticate as LaramieAuthenticate;
use Laramie\Http\Middleware\Authorize as LaramieAuthorize;
use Laramie\Http\Middleware\RequestLogger;
use Laramie\Http\Middleware\ShareAlertFromSession;

/*
 * Register the API routes -- these routes are protected by basic auth (the
 * username and password are properties on LaramieUsers).
 *
 * Currently only read-only routes are available (list and detail).
 */
Route::group(
    [
        'middleware' => [LaramieApiAuthenticate::class, RequestLogger::class],
        'namespace' => '\Laramie\Http\Controllers',
        'prefix' => config('laramie.admin_url').'/api',
        'as' => 'laramie::api',
    ],
    function () {
        Route::get('{modelKey}/{id}', 'ApiController@getItem')->name('api-get-item')->middleware(LaramieAuthorize::class);
        Route::get('{modelKey}', 'ApiController@getList')->name('api-get-list')->middleware(LaramieAuthorize::class);
    }
);

/*
 * Register the admin routes.
 */
Route::group(
    [
        'middleware' => ['web', 'auth', LaramieAuthenticate::class, RequestLogger::class],
        'namespace' => '\Laramie\Http\Controllers',
        'prefix' => config('laramie.admin_url'),
        'as' => 'laramie::',
    ],
    function () {
        Route::get('/', 'AdminController@getDashboard')->name('dashboard')->middleware(LaramieAuthorize::class);
        Route::post('/save-report/{modelKey}', 'AdminController@saveReport')->name('save-report')->middleware(LaramieAuthorize::class);
        Route::post('/bulk-actions/{modelKey}', 'AdminController@bulkActionHandler')->name('bulk-action-handler')->middleware(LaramieAuthorize::class);

        Route::get('/mfa-register', 'MFAController@getRegister')->name('mfa-register');
        Route::post('/mfa-register', 'MFAController@postRegister');
        Route::get('/mfa-login', 'MFAController@getLogin')->name('mfa-login');
        Route::post('/mfa-login', 'MFAController@postLogin');

        Route::get('/assets/icon/{imageKey}', 'AssetController@showIcon')->name('icon');
        Route::get('/assets/image/{imageKey}', 'AssetController@showImage')->name('image');
        Route::get('/assets/file/{assetKey}', 'AssetController@downloadFile')->name('file-download');
        Route::get('/assets/cropper/{imageKey}', 'AssetController@showCropper')->name('cropper')->middleware(ShareAlertFromSession::class);
        Route::post('/assets/cropper/{imageKey}', 'AssetController@cropImage');

        Route::post('/list-prefs/{modelKey}', 'AdminController@saveListPrefs')->name('save-list-prefs')->middleware(LaramieAuthorize::class);
        Route::post('/report/modify/{id}', 'AdminController@modifyReport')->name('modify-report');
        Route::get('/report/{id}', 'AdminController@loadReport')->name('load-report');
        Route::post('/ajax/markdown', 'AjaxController@markdownToHtml')->name('ajax-markdown');
        Route::post('/ajax/edit-prefs', 'AjaxController@saveEditPrefs')->name('save-edit-prefs');
        Route::get('/ajax/{modelKey}/{listModelKey}', 'AjaxController@getList')->name('ajax-list')->middleware(LaramieAuthorize::class);
        Route::get('/ajax/meta/{modelKey}/{id}', 'AjaxController@getMeta')->name('load-meta')->middleware(LaramieAuthorize::class);
        Route::post('/ajax/meta/{modelKey}/delete/{id}', 'AjaxController@deleteMeta')->name('delete-meta')->middleware(LaramieAuthorize::class);
        Route::post('/ajax/meta/{modelKey}/{id}/add-tag', 'AjaxController@addTag')->name('add-tag')->middleware(LaramieAuthorize::class);
        Route::post('/ajax/meta/{modelKey}/{id}/add-comment', 'AjaxController@addComment')->name('add-comment')->middleware(LaramieAuthorize::class);
        Route::post('/ajax/dismiss-alert/{id}', 'AjaxController@dismissAlert');

        Route::get('/revisions/compare/{modelKey}/{revisionId}', 'AdminController@compareRevisions')->name('compare-revisions')->middleware(LaramieAuthorize::class);
        Route::post('/revisions/restore/{modelKey}/{revisionId}', 'AdminController@restoreRevision')->name('restore-revision')->middleware(LaramieAuthorize::class);
        Route::post('/revisions/trash/{modelKey}/{revisionId}', 'AdminController@deleteRevision')->name('trash-revision')->middleware(LaramieAuthorize::class);

        Route::get('/alert/{id}', 'AdminController@alertRedirect')->name('alert-redirector');

        Route::get('/{modelKey}/{id}', 'AdminController@getEdit')->name('edit')->middleware([LaramieAuthorize::class, ShareAlertFromSession::class]);
        Route::post('/{modelKey}/{id}', 'AdminController@postEdit')->name('post-edit')->middleware(LaramieAuthorize::class);
        Route::delete('/{modelKey}/{id}', 'AdminController@deleteItem')->name('delete-item')->middleware(LaramieAuthorize::class);
        Route::get('/{modelKey}', 'AdminController@getList')->name('list')->middleware(LaramieAuthorize::class);
    }
);
