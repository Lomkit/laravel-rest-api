<?php

namespace Lomkit\Rest\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class EnforceExpectsJson
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $accept = $request->header('Accept', '');

        if (! Str::contains($accept, 'application/json')) {
            $request->headers->set('Accept', 'application/json, '.$accept);
        }

        return $next($request);
    }
}
