<?php

declare(strict_types=1);

namespace Laramie\Hooks;

/*
 * Called after registering Laramie routes -- useful for overriding specific
 * admin-level routes.
 */
class OverrideAdminRoutes
{
    public $router;

    /**
     * Create a new OverrideAdminRoutes hook.
     */
    public function __construct(&$router)
    {
        $this->router = $router;
    }
}
