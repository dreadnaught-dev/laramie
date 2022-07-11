<?php

namespace Laramie\Http\Middleware;

use DB;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Factory as Auth;

use Laramie\Globals;
use Laramie\Hook;
use Laramie\Services\LaramieDataService;
use Laramie\LaramieUser;
use Laramie\Hooks\LaramieUserAuthenticated;

class Authenticate
{
    /**
     * The authentication factory instance.
     *
     * @var \Illuminate\Contracts\Auth\Factory
     */
    protected $auth;

    /**
     * Create a new middleware instance.
     *
     * @param \Illuminate\Contracts\Auth\Factory $auth
     */
    public function __construct(Auth $auth)
    {
        $this->auth = $auth;
    }

    /**
     * Handle an incoming request.
     *
     * To be authenticated in Laramie, a user must be authenticated via Laravel's auth first. Once they are, the
     * following middleware comes into play.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure                 $next
     * @param string[]                 ...$guards
     *
     * @return mixed
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function handle($request, Closure $next)
    {
        $linkedField = config('laramie.username');
        $userUuid = null;

        // If we're on a mfa route, skip. We need to allow those routes through.
        if (strpos($request->route()->getActionName(), 'MFAController') !== false) {
            return $next($request);
        }

        // If the user is already logged in, we don't need go any further
        if (!$request->session()->has('_laramie')) {
            // Get the laramieUser that's linked to the Laravel one (via `$linkedField`)
            $user = LaramieUser::superficial()
                ->where('user', '=', $this->auth->user()->{$linkedField})
                ->where('status', '=', 'Active')
                ->first();

            if ($user) {
                // Check two-factor authentication (can be enabled/disabled at the application or user level)
                $mfaGloballyEnabled = config('laramie.enable_mfa', false);

                if ($mfaGloballyEnabled
                    && data_get($user, 'mfa.enabled')
                    && !$request->session()->has('_mfa')
                ) {
                    $request->session()->put('url.intended', url()->current());
                    // Is the user registered? Attempt to authenticate them. Otherwise, register them:
                    if (data_get($user, 'mfa.registrationCompleted')) {
                        return redirect()->to(route('laramie::mfa-login'));
                    } else {
                        return redirect()->to(route('laramie::mfa-register'));
                    }
                }

                // Save all the above processing to the user's session so we don't have to do it on every request
                $request->session()->put('_laramie', $user->id);
                $userUuid = $user->id;

                Hook::fire(new LaramieUserAuthenticated($user));
            }
        }

        // At this point a user should have been authenticated by above, or have session info set. If not, throw an auth
        // exception.
        if ($userUuid === null && $request->session()->get('_laramie') === null) {
            throw new AuthorizationException('You are not authorized for access.');
        }

        return $next($request);
    }
}
