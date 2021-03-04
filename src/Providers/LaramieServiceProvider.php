<?php

namespace Laramie\Providers;

use Arr;
use DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Validator;

use Laramie\AdminModels\LaramieAlert;
use Laramie\Http\Middleware\RequestLogger;
use Laramie\Lib\LaramieHelpers;
use Laramie\Hook;
use Laramie\Services\LaramieDataService;

/*
 * FOR ANY FACADE DEPENDENCY:
 * https://laravel.com/docs/5.4/facades:
 *
 * @todo -- When building a third-party package that interacts with Laravel, it's better
 * to inject Laravel contracts instead of using facades. Since packages are
 * built outside of Laravel itself, you will not have access to Laravel's
 * facade testing helpers.
 */

class LaramieServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot()
    {
        // Tell Laravel to publish Laramie's config
        $this->publishes([
            __DIR__.'/../config/laramie.php' => config_path('laramie.php'),
            __DIR__.'/../public' => public_path('laramie/admin'),
            __DIR__.'/../resources/views/dashboard.blade.php' => resource_path('views/vendor/laramie/dashboard.blade.php'),
            __DIR__.'/../models' => base_path('models'),
        ], 'laramie');

        $this->registerCustomCommands();

        // Tell Laravel to load Laramie's migrations
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // Tell Laravel to load Laramie's routes
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        // Tell Laravel where to load Laramie's views from
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'laramie');

        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'laramie');

        // Create a request logger singleton -- otherwise [Laravel will resolve a fresh instance of the middleware from the service container](https://laravel.com/docs/middleware#terminable-middleware).
        $this->app->singleton(RequestLogger::class, function ($app) {
            return new RequestLogger();
        });

        // Create a LaramieDataService singleton
        $this->app->singleton(LaramieDataService::class, function ($app) {
            return new LaramieDataService();
        });

        // Inject data into views and partials as needed
        \View::composer(['laramie::layout'], function ($view) {
            $systemUsers = DB::table('users')
                ->whereRaw(DB::raw('jsonb_exists(laramie, \'roles\')')) // TODO -- do we need to add a more specific attribute? assumption is that anyone with roles is some sort of backend user.
                ->select([config('laramie.username')])
                ->get()
                ->pluck(config('laramie.username'));

            $view->with('systemUsers', $systemUsers);
        });

        // Inject data into views and partials as needed
        \View::composer(['laramie::partials.header'], function ($view) {
            $alerts = LaramieAlert::where(\DB::raw('data->>\'status\''), '=', 'Unread')->get();
            $view->with('alerts', $alerts);
        });

        // Inject data into views and partials as needed
        \View::composer(['laramie::partials.left-nav', 'laramie::partials.header'], function ($view) {
            $view->with('menu', app(LaramieDataService::class)->getMenu());

            // If an app / plugin has already set a `user` attribute for this view, use it instead.
            if (!data_get($view->getData(), 'user')) {
                $view->with('user', app(LaramieDataService::class)->getUser());
            }
        });

        // Add a new blade directive, which allows a fallback partial if the first isn't found
        Blade::directive('includeIfFallback', function ($expression) {
            $view = preg_replace('/,.*$/', '', $expression);
            $fallback = preg_replace('/^.*,/', '', $expression);

            return <<<EOT
            <?php
                if (\$__env->exists({$view})) {
                    echo \$__env->make({$view}, Arr::except(get_defined_vars(), array('__data', '__path')))->render();
                } elseif (\$__env->exists({$fallback})) {
                    echo \$__env->make({$fallback}, Arr::except(get_defined_vars(), array('__data', '__path')))->render();
                }
            ?>
EOT;
        });

        Validator::extend('laramie_image', function ($attribute, $value, $parameters, $validator) {
            $extension = $value->getClientOriginalExtension();
            return in_array(strtolower($extension), $parameters);
        });

        Validator::replacer('laramie_image', function ($message, $attribute, $rule, $parameters) {
            return 'This must be a file of type:' . implode(', ', $parameters);
        });
    }

    /**
     * Register application services.
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/laramie.php', 'laramie');
    }

    /**
     * Register Laramie console commands.
     */
    private function registerCustomCommands()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                \Laramie\Console\Commands\AuthorizeLaramieUser::class,
                \Laramie\Console\Commands\ClearModelCache::class,
            ]);
        }
    }
}
