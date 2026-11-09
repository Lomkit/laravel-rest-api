<?php

namespace Lomkit\Rest\Tests\Feature\Metrics\Stubs;

use Lomkit\Rest\Http\Controllers\Controller;

class TaggedTicketController extends Controller
{
    public static string $resource = TaggedTicketResource::class;
}
