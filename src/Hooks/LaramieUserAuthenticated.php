<?php

namespace Laramie\Hooks;

use Laramie\LaramieUser;

/*
 * Called immediately after a user logs in _and_ the Laramie user has been attached 
 * to the session.
 */
class LaramieUserAuthenticated
{
    public $user;    

    public function __construct(LaramieUser $user)
    {
        $this->user = $user;
    }
}
