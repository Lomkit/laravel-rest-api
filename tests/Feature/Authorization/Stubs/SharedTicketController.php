<?php

namespace Lomkit\Rest\Tests\Feature\Authorization\Stubs;

use Lomkit\Rest\Http\Controllers\Controller;

class SharedTicketController extends Controller
{
    public static string $resource = SharedTicketResource::class;
}
