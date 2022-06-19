<?php

declare(strict_types=1);

namespace Laramie\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;

class LaramieEventServiceProvider extends ServiceProvider
{
    /**
     * The event listener subscribers for Laramie.
     *
     * @var array
     */
    protected $subscribe = [
        'Laramie\Listeners\LaramieListener',
    ];

    /**
     * Register any events for your application.
     */
    public function boot()
    {
        parent::boot();
    }
}
