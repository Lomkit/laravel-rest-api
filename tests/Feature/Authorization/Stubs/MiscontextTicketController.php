<?php

namespace Lomkit\Rest\Tests\Feature\Authorization\Stubs;

use Lomkit\Rest\Http\Controllers\Controller;

class MiscontextTicketController extends Controller
{
    public static string $resource = MiscontextTicketResource::class;
}
