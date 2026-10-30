<?php

namespace Lomkit\Rest\Tests\Fixtures\Helpdesk;

use Lomkit\Rest\Http\Controllers\Controller;

class TicketController extends Controller
{
    public static string $resource = TicketResource::class;
}
