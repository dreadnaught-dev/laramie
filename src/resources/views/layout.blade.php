<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta content="IE=edge" http-equiv="X-UA-Compatible">
    <meta content="width=device-width, initial-scale=1" name="viewport">
    <meta content="Laramie is an open source, headless CMS built on Laravel and modern web technologies. Npm-install it and you're good to go" name="description">
    <title>Laramie - control your content</title>
    <link href="/laramie/admin/css/main.css" rel="stylesheet">
    <link href="/laramie/admin/css/dragula.min.css" rel="stylesheet">
    <link href="/laramie/admin/css/tribute.css" rel="stylesheet">
    <link href="/laramie/admin/css/select2.min.css" rel="stylesheet">
    <meta content="#00d1b2" name="theme-color">
    <script>
        window.globals = window.globals || {};
        window.globals._token = '{{ csrf_token() }}';
        window.globals.cropperBase = '{{ preg_replace('/new$/', '', route('laramie::cropper', ['imageKey' => 'new'])) }}';
        window.globals.fileDownloadBase = '{{ preg_replace('/new$/', '', route('laramie::file-download', ['key' => 'new'])) }}';
        window.globals.adminUrl = '{{ config('laramie.admin_url') }}';
        window.globals.systemUsers = {!! json_encode($systemUsers) !!};
    </script>
    @stack('extra-header')
</head>
<body class="body">
    @include('laramie::partials.header')
    <section class="content-wrapper section">
        <div class="container is-fluid">
            <div class="columns">
                <div class="column is-2 is-hidden-touch">
                    @include('laramie::partials.left-nav')
                </div>
                @yield('content')
            </div>
        </div>
    </section>
    <footer class="footer">
        <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
            {{ csrf_field() }}
        </form>
        <div class="container is-fluid">
            <div class="has-text-centered">
                Powered by <a href="https://github.com/dreadnaught-dev/laramie"><strong style="text-decoration: underline; text-decoration-skip: ink;">Laramie</strong></a>
                <a style="margin-left: 1.5rem;" href="https://github.com/dreadnaught-dev/laramie" target="_blank">
                    <span class="icon" style="color: #333;">
                        <i class="fab fa-github"></i>
                    </span>
                </a>
            </div>
        </div>
    </footer>
    <script src="/laramie/admin/js/jquery.min.js"></script>
    <script src="/laramie/admin/js/handlebars.min.js"></script>
    <script src="/laramie/admin/js/dragula.min.js"></script>
    <script src="/laramie/admin/js/tribute.min.js"></script>
    <script src="/laramie/admin/js/select2.min.js"></script>
    <script src="/laramie/admin/js/main.js"></script>
    <script src="/laramie/admin/js/fontawesome/all.js"></script>
    @stack('scripts')
</body>
</html>
