<?php

namespace App\Http\Middleware;

use App\Models\AccessLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class LogRouteAccess
{
    private const METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    /**
     * Record mutating requests after the response is sent, so logging never
     * adds latency and we can read the final status code.
     */
    public function terminate(Request $request, Response $response): void
    {
        if (! in_array($request->method(), self::METHODS, true)) {
            return;
        }

        AccessLog::create([
            'user_id' => Auth::id(),
            'method' => $request->method(),
            'path' => $request->path(),
            'route_name' => $request->route()?->getName(),
            'status' => $response->getStatusCode(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}
