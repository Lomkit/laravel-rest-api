<?php

namespace Lomkit\Rest\Tests\Feature\Authorization\Stubs;

use Lomkit\Rest\Http\Controllers\Controller;

class HookedTicketController extends Controller
{
    public static string $resource = HookedTicketResource::class;
}
