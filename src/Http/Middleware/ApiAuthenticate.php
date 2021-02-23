<?php

namespace Laramie\Http\Middleware;

use Illuminate\Support\Facades\Auth;
use Illuminate\Auth\Access\AuthorizationException;

use Laramie\Globals;
use Laramie\LaramieUser;
use Laramie\Services\LaramieDataService;

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
        if ($request->hasSession() && $request->session()->has('_laramie')) {
            $a = new Authenticate(auth());
            return $a->handle($request, $next);
        }

        // Get the username and password from request headers. Leverage Laravel's `Auth::onceUsingId` if a corresponding Laramie user is found
        // The username and password are not the user's acutal username and password, but correspond to the user's `api` username and password.
        $authArray = explode(':', base64_decode(trim(str_replace('Basic', '', $request->header('Authorization', '')))));

        // First find the laramieUser that corresponds to those creds:
        $laramieUser = array_first(\DB::select('select id, data->>\'user\' as user from laramie_data where type = \'laramieUser\' and (data#>>\'{api,enabled}\')::boolean = true and data#>>\'{api,username}\'= ? and data#>>\'{api,password}\' = ? limit 1', [array_get($authArray, 0, -1), array_get($authArray, 1, -1)]));

        // Next find the Laravel user that corresponds to the Laramie user:
        $laravelUser = array_first(\DB::select('select id from users where '.config('laramie.username').' like ?', [data_get($laramieUser, 'user')]));

        // Using Laravel, log them in by their id (if it exists)
        $success = Auth::onceUsingId(data_get($laravelUser, 'id', -1));

        if ($success) {
            // Success, creds match, users match, etc. Now find and set their access rights / abilities:
            $laramieDataService = app(LaramieDataService::class);
            $laramieUser = LaramieUser::find($laramieUser->id);

            if ($request->hasSession()) {
                $request->session()->put('_laramie', $user->id);
            }
            else {
                Auth::user()->_laramie = $laramieUser;
            }

            return $next($request);
        }

        // Something went wrong, throw auth exception:
        throw new AuthorizationException('You are not authorized for access.');
    }
}
