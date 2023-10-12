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
     * @param \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response) $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!Str::contains($request->header('Accept'), 'application/json')) {
            $request->headers->set('Accept', 'application/json, '.$request->header('Accept'));
        }

        return $next($request);
    }
}
