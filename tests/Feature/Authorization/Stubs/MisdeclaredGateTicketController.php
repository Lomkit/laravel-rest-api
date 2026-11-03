<?php

namespace Lomkit\Rest\Tests\Feature\Authorization\Stubs;

use Lomkit\Rest\Http\Controllers\Controller;

class MisdeclaredGateTicketController extends Controller
{
    public static string $resource = MisdeclaredGateTicketResource::class;
}
