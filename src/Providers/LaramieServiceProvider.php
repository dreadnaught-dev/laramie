<?php

namespace Laramie\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Validator;

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
            $view->with('systemUsers',
                collect(app(LaramieDataService::class)->findByType(
                    'laramieUser',
                    ['resultsPerPage' => 0, 'forSystemUsers' => 1],
                    function ($query) {
                        $query->where(\DB::raw('data->>\'status\''), '=', 'Active');
                    },
                    0)
                )
                ->map(function ($item) { return $item->user; })
            );
        });

        // Inject data into views and partials as needed
        \View::composer(['laramie::partials.header'], function ($view) {
            $service = app(LaramieDataService::class);
            $alerts = $service->findByType(
                    'laramieAlert',
                    ['resultsPerPage' => 0],
                    function ($query) use ($service) {
                        $query->where(\DB::raw('data->>\'recipient\''), '=', $service->getUserUuid())
                            ->where(\DB::raw('data->>\'status\''), '=', 'Unread');
                    }
                )
                ->map(function ($item) {
                    $item->html = object_get($item, 'message.html');
                    $item->_user = $item->author->user;

                    return LaramieHelpers::transformCommentForDisplay($item);
                });
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
                    echo \$__env->make({$view}, array_except(get_defined_vars(), array('__data', '__path')))->render();
                } elseif (\$__env->exists({$fallback})) {
                    echo \$__env->make({$fallback}, array_except(get_defined_vars(), array('__data', '__path')))->render();
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
        $commands = [\Laramie\Console\Commands\ClearUserPrefs::class];

        if ($this->app->runningInConsole()) {
            $commands[] = \Laramie\Console\Commands\AuthorizeLaramieUser::class;
            $commands[] = \Laramie\Console\Commands\ClearModelCache::class;
        }

        $this->commands($commands);
    }
}
