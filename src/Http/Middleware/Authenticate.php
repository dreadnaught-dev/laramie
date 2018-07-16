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
        // If we're on a two-factor/* route, skip. We need to allow those routes through.
        if (strpos($request->url(), 'two-factor') !== false) {
            return $next($request);
        }

        $linkedField = config('laramie.username');
        $userUuid = null;

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
                $dualAuthEnabled = config('laramie.enable_dual_auth') &&
                    config('laramie.duo.integrationKey') &&
                    config('laramie.duo.secretKey') &&
                    config('laramie.duo.apiHostname');

                if ($dualAuthEnabled && object_get($user, 'twoFactorAuthentication.enabled') && !$request->session()->has('_two_factor')) {
                    $request->session()->put('url.intended', url()->current());
                    // Is the user registered? Attempt to authenticate them. Otherwise, register them:
                    if (object_get($user, 'twoFactorAuthentication.id')) {
                        return redirect()->to(route('laramie::duo-login'));
                    } else {
                        return redirect()->to(route('laramie::duo-register'));
                    }
                }

                // Collect all non-system models (non-core models, like LaramieUser, etc). We'll be checking the user's
                // access to each.
                $nonSystemModels = collect($laramieDataService->getAllModels())
                    ->filter(function ($e) {
                        return !object_get($e, 'isSystemModel');
                    })
                    ->sortBy(function ($e) {
                        return $e->namePlural;
                    })
                    ->keys()
                    ->all();

                $userRoles = object_get($user, 'role', []);
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
                    foreach ($nonSystemModels as $modelType) {
                        if (object_get($role, $modelType, false) === true) {
                            // Add the model as an "ability" if the user has access
                            $abilities[] = $modelType;
                        }
                    }
                }

                $user->isSuperAdmin = $isSuperAdmin;
                $user->isAdmin = $isAdmin;
                $user->abilities = $abilities;

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
