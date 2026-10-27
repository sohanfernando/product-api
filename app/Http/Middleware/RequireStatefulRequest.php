<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireStatefulRequest
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->attributes->get('sanctum') && $request->hasSession(), 403,
            'Send an Origin or Referer matching SANCTUM_STATEFUL_DOMAINS to use cookie authentication.');

        return $next($request);
    }
}
