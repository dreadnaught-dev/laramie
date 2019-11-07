<?php

namespace Laramie\Http\Middleware;

use Laramie\Services\LaramieDataService;
use Laramie\Globals;
use DB;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Factory as Auth;

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

        // If the user is already logged, we don't need go any further
        if (!$request->session()->has('_laramie')) {
            // Get the LaramieUser that's linked to the Laravel one (via `$linkedField`)
            $userRecord = DB::table('laramie_data')
                ->where('type', 'LaramieUser')
                ->where(DB::raw('data->>\'user\''), '=', $this->auth->user()->{$linkedField})
                ->where(DB::raw('data->>\'status\''), '=', 'Active')
                ->first();

            if ($userRecord) {
                $laramieDataService = app(LaramieDataService::class);
                $user = $laramieDataService->findById($laramieDataService->getModelByKey('LaramieUser'), $userRecord->id);

                // Check two-factor authentication (can be enabled/disabled at the application or user level)
                $mfaGloballyEnabled = config('laramie.enable_mfa', false);

                if ($mfaGloballyEnabled
                    && object_get($user, 'mfa.enabled')
                    && !$request->session()->has('_mfa')
                ) {
                    $request->session()->put('url.intended', url()->current());
                    // Is the user registered? Attempt to authenticate them. Otherwise, register them:
                    if (object_get($user, 'mfa.registrationCompleted')) {
                        return redirect()->to(route('laramie::mfa-login'));
                    } else {
                        return redirect()->to(route('laramie::mfa-register'));
                    }
                }

                $userRoles = object_get($user, 'roles', []);
                $abilities = [];
                $isSuperAdmin = false;
                $isAdmin = false;

                foreach ($userRoles as $role) {
                    $isSuperAdmin = $isSuperAdmin || $role->id == Globals::SuperAdminRoleId;
                    $isAdmin = $isAdmin || $role->id == Globals::AdminRoleId;
                    if ($isSuperAdmin || $isAdmin) {
                        // Don't dive deeper if the user is an admin or super admin -- both have access to all non system models (see [Authorize.md](Authorize.md).
                        continue;
                    }
                    // The `data` attribute contains the abilities the particular role has been granted
                    collect(json_decode(data_get($role->toArray(), 'data')))
                        ->filter(function($item) { return $item === true; })
                        ->each(function($item, $key) use(&$abilities) { $abilities[$key] = true; });
                }

                $user->isSuperAdmin = $isSuperAdmin;
                $user->isAdmin = $isAdmin;
                $user->abilities = array_keys($abilities);

                // Save all the above processing to the user's session so we don't have to do it on every request
                $request->session()->put('_laramie', $user);
                $userUuid = $user->id;
            }
        }

        // At this point a user should have been authenticated by above, or have session info set. If not, throw an auth
        // exception.
        if ($userUuid === null && object_get($request->session()->get('_laramie'), 'id') === null) {
            throw new AuthorizationException('You are not authorized for access.');
        }

        return $next($request);
    }
}
