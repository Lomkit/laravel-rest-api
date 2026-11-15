<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class StampsRequests
{
    /** @var list<string> */
    public static array $stamps = [];

    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next, string $stamp = ''): Response
    {
        self::$stamps[] = $stamp;

        return $next($request);
    }
}
