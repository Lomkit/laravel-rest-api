<?php

namespace Lomkit\Rest\Tests\Fixtures\Helpdesk;

use Lomkit\Rest\Http\Controllers\Controller;

class UncachedTicketController extends Controller
{
    public static string $resource = UncachedTicketResource::class;
}
