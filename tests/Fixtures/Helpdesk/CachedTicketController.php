<?php

namespace Lomkit\Rest\Tests\Fixtures\Helpdesk;

use Lomkit\Rest\Http\Controllers\Controller;

class CachedTicketController extends Controller
{
    public static string $resource = CachedTicketResource::class;
}
