<?php

declare(strict_types=1);

namespace Laramie\Hooks;

/*
 * Called before registering Laramie routes -- useful for pre-empting wildcard
 * admin-level. For example, if you'd like to create a /admin/profile route,
 * register it within this hook.
 */
class RegisterAdminRoutes
{
    public $router;

    /**
     * Create a new RegisterAdminRoutes hook.
     */
    public function __construct(&$router)
    {
        $this->router = $router;
    }
}
