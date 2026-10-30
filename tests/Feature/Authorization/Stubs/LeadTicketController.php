<?php

namespace Lomkit\Rest\Tests\Feature\Authorization\Stubs;

use Lomkit\Rest\Http\Controllers\Controller;

class LeadTicketController extends Controller
{
    public static string $resource = LeadTicketResource::class;
}
