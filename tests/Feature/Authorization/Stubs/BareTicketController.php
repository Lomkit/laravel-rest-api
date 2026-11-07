<?php

namespace Lomkit\Rest\Tests\Feature\Authorization\Stubs;

use Lomkit\Rest\Http\Controllers\Controller;

class BareTicketController extends Controller
{
    public static string $resource = BareTicketResource::class;
}
