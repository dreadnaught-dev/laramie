<?php

namespace Laramie\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

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
