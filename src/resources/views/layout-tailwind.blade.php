<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta content="IE=edge" http-equiv="X-UA-Compatible">
        <meta content="width=device-width, initial-scale=1" name="viewport">
        <meta content="Laramie is an open source, headless CMS built on Laravel and modern web technologies. Npm-install it and you're good to go" name="description">
        <title>Laramie - control your content</title>
        <link href="/laramie/admin/css/tailwind.css" rel="stylesheet">
        <?php /*
        <link href="/laramie/admin/css/dragula.min.css" rel="stylesheet">
        <link href="/laramie/admin/css/tribute.css" rel="stylesheet">
        <link href="/laramie/admin/css/select2.min.css" rel="stylesheet">
        */ ?>
        <meta content="#00d1b2" name="theme-color">
        <script>
            window.globals = window.globals || {};
            window.globals._token = '{{ csrf_token() }}';
            window.globals.cropperBase = '{{ preg_replace('/new$/', '', route('laramie::cropper', ['imageKey' => 'new'])) }}';
            window.globals.fileDownloadBase = '{{ preg_replace('/new$/', '', route('laramie::file-download', ['assetKey' => 'new'])) }}';
            window.globals.adminUrl = '{{ config('laramie.admin_url') }}';
            window.globals.systemUsers = {!! json_encode($systemUsers) !!};
        </script>
        @stack('extra-header')
    </head>
    <body>
        <?php /*// https://iconhub.io/?style=line */?>
        <div class="leading-none font-sans text-gray-800 antialiased">
            <div class="flex">
                <div class="flex-initial w-1/4">
                    HELLO
                </div>
                <div class="flex-1">
                    HELLO
                </div>
            </div>
        </div>
    </body>
</html>
