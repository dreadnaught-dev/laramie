<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta content="IE=edge" http-equiv="X-UA-Compatible">
    <meta content="width=device-width, initial-scale=1" name="viewport">
    <meta content="Laramie is an open source, headless CMS built on Laravel and modern web technologies. Npm-install it and you're good to go" name="description">
    <title>Laramie - control your content</title>
    <link href="/laramie/admin/css/main.css" rel="stylesheet">
    <meta content="#00d1b2" name="theme-color">
    @stack('extra-header')
</head>
<body class="body">

    <section class="hero is-light is-fullheight">
        <div class="hero-body">
            <div class="container">
                <div class="columns is-centered is-mobile">
                    <div class="column is-half">
                        <div class="card">
                            <div class="card-content content">
                                @yield('content')
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    @stack('scripts')
    <script src="/laramie/admin/js/jquery.min.js"></script>
    <script>
        $(document).ready(function(){
            $('[name="mfa"]').focus();
        });
    </script>
</body>
</html>
