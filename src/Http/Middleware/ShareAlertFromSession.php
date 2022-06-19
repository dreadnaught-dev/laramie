<?php

declare(strict_types=1);

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
     *
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $this->view->share(
            'alert',
            $request->session()->get('alert') ?: null
        );

        $this->view->share(
            'systemMessage',
            preg_match('/\bis-child\b/', $request->fullUrl())
                ? (object) ['alert' => 'It looks like you\'re visiting this page from an internal link (and likely have a tab open elsewhere that you were looking at before). Once you\'re done here, you may want to <a href="javascript:close()">close this tab</a> and jump back to the other one. Otherwise, feel free to <a href="'.preg_replace('/[?&]is-child=\d/i', '', request()->fullUrl()).'">dismiss this message</a> and continue on.', 'class' => 'is-warning']
                : null
        );

        return $next($request);
    }
}
