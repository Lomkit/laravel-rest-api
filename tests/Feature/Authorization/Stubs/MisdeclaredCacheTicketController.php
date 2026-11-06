<?php

namespace Lomkit\Rest\Tests\Feature\Authorization\Stubs;

use Lomkit\Rest\Http\Controllers\Controller;

class MisdeclaredCacheTicketController extends Controller
{
    public static string $resource = MisdeclaredCacheTicketResource::class;
}
