<?php

namespace Laramie\Http\Middleware;

use Closure;
use Illuminate\Contracts\View\Factory as ViewFactory;

class ShareAlertFromSession
{
    /**
     * The view factory implementation.
     *
     * @var \Illuminate\Contracts\View\Factory
     */
    protected $view;

    /**
     * Create a new ShareAlertFromSession instance.
     *
     * @param \Illuminate\Contracts\View\Factory $view
     */
    public function __construct(ViewFactory $view)
    {
        $this->view = $view;
    }

    /**
     * Handle an incoming request.
     *
     * Putting the alerts in the view for every view allows the developer to just assume that some alerts are always
     * available, which is convenient since they don't have to continually run checks for the presence of alerts.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure                 $next
     *
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $this->view->share(
            'alert',
            $request->session()->get('alert') ?: null
        );

        return $next($request);
    }
}
