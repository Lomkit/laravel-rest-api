<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Lomkit\Rest\Http\Controllers\Controller;

class DottedContextTicketController extends Controller
{
    public static string $resource = DottedContextTicketResource::class;
}
