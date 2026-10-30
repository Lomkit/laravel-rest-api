<?php

namespace Lomkit\Rest\Tests\Fixtures\Helpdesk;

use Lomkit\Rest\Http\Controllers\Controller;

class PublicTicketController extends Controller
{
    public static string $resource = PublicTicketResource::class;
}
