<?php

declare(strict_types=1);

namespace Laramie\Http\Middleware;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;

class ApiAuthenticate
{
    /**
     * Authenticate an incoming API request.
     *
     * API requests are protected via basic authentication. The username and password are not the user's acutal username
     * and password, but correspond to the user's `api` username and password, which are created by Laramie when a new
     * user is created via the admin or added via the cli.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure                 $next
     *
     * @return mixed
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function handle($request, $next)
    {
        // API enabled for web? Use normal auth... TODO -- validate this works as expected
        if ($request->hasSession()) {
            return $next($request);
        }

        // Get the username and password from request headers. Leverage Laravel's `Auth::onceUsingId` if a corresponding user is found.
        // The username and password are not the user's acutal username and password, but correspond to the user's `api` username and password.
        $authArray = explode(':', base64_decode(trim(str_replace('Basic', '', $request->header('Authorization', '')))));

        // First find the user that corresponds to those creds:
        $user = User::where(DB::raw('laramie#>>\'{api,enabled}\')::boolean'), true)
            ->where(DB::raw('laramie#>>\'{api,username}\''), data_get($authArray, 0, -1))
            ->where(DB::raw('laramie#>>\'{api,password}\''), data_get($authArray, 1, -1))
            ->first();

        // Log them in by their id (if it exists)
        $success = Auth::onceUsingId(data_get($user, 'id', -1));

        if ($success) {
            return $next($request);
        }

        // Something went wrong, throw auth exception:
        throw new AuthorizationException('You are not authorized for access.');
    }
}
