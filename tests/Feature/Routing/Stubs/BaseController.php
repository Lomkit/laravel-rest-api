<?php

namespace Lomkit\Rest\Tests\Feature\Routing\Stubs;

use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Lomkit\Rest\Http\Controllers\Controller;

abstract class BaseController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            'throttle:5,1',
            new Middleware('auth', only: ['destroy']),
        ];
    }
}
