<?php

namespace Laramie\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\Factory as Auth;

class Authorize
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
     * Check to see if the user is authorized to access a model type.
     *
     * This middleware is highly dependent on convention. Namely, it really only applies to routes that have a
     * `modelKey` parameter. If the route does, then this middleware ensures that the user is authorized to access that
     * model type.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure                 $next
     *
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if ($this->auth->check() && $request->route()->hasParameter('modelKey')) {
            $modelKey = $request->route()->parameter('modelKey');
            $laramieUser = object_get($this->auth->user(), '_laramie', ($request->hasSession() ? $request->session()->get('_laramie') : null));
            $hasAccess = $laramieUser->isSuperAdmin
               || $laramieUser->isAdmin
               || in_array($modelKey, $laramieUser->abilities);
            if (!$hasAccess) {
                abort(403, 'Unauthorized.');
            }
        }

        return $next($request);
    }
}
