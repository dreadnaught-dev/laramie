<?php

declare(strict_types=1);

namespace Laramie\Http\Middleware;

use Closure;

/**
 * Log all actions handled by Laramie. Adapted from:
 * https://laracasts.com/discuss/channels/laravel/logging-request-and-response-with-middleware.
 */
class RequestLogger
{
    protected $start;
    protected $end;

    /**
     * Create a new RequestLogger instance.
     */
    public function __construct()
    {
    }

    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $this->start = microtime(true);

        return $next($request);
    }

    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request  $request
     * @param \Illuminate\Http\Response $response
     */
    public function terminate($request, $response)
    {
        $this->end = microtime(true);

        $this->log($request, $response);
    }

    /**
     * Log request info.
     *
     * @param \Illuminate\Http\Request  $request
     * @param \Illuminate\Http\Response $response
     *
     * @return mixed
     */
    protected function log($request, $response)
    {
        $duration = ($this->end - $this->start) * 1000;
        $user = data_get($request->user(), config('laramie.username'), 'non-user');
        $url = $request->fullUrl();
        $method = $request->getMethod();
        $ip = $request->getClientIp();
        $status = $response->getStatusCode();
        $log = "{$ip} - {$user} \"{$method} {$url}\" {$status} - {$duration}ms";

        \Log::info($log);
    }
}
