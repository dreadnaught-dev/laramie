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
        $loggedInUserId = $request->session()->get('_laramie');
        $user = null;

        // If we're on a mfa route, skip. We need to allow those routes through.
        if (strpos($request->route()->getActionName(), 'MFAController') !== false) {
            return $next($request);
        }

        // TODO -- can we refactor by moving some of this logic based on Laravel's Login method (so move the logic to set session vars to event listener)?

        $user = $loggedInUserId
            ? LaramieUser::filterQuery(false)->find($loggedInUserId)
            : LaramieUser::where('user', '=', $this->auth->user()->{$linkedField})->where('status', '=', 'Active')->first();

        // If a user's first request since logging in:
        if (!$loggedInUserId && $user) {
            // Save all the above processing to the user's session so we don't have to do it on every request
            $loggedInUserId = $user->id;
            $request->session()->put('_laramie', $loggedInUserId);

            Hook::fire(new LaramieUserAuthenticated($user));
        }

        // Check to see if we need to present the user MFA screens:
        $mfaRequired = !$request->get('skipMfa');
            && $user
            && config('laramie.enable_mfa', false)
            && object_get($user, 'mfa.enabled')
            && !$request->session()->has('_mfa');

        if ($mfaRequired) {
            $request->session()->put('url.intended', url()->current());
            // Is the user registered? Attempt to authenticate them. Otherwise, register them:
            if (object_get($user, 'mfa.registrationCompleted')) {
                return redirect()->to(route('laramie::mfa-login'));
            } else {
                return redirect()->to(route('laramie::mfa-register'));
            }
        }

        // At this point a user should have been authenticated by above, or have session info set. If not, throw an auth
        // exception.
        if ($loggedInUserId === null && $request->session()->get('_laramie') === null) {
            throw new AuthorizationException('You are not authorized for access.');
        }

        return $next($request);
    }

}
