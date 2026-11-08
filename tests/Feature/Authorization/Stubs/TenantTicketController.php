<?php

namespace Lomkit\Rest\Tests\Feature\Authorization\Stubs;

use Lomkit\Rest\Http\Controllers\Controller;

class TenantTicketController extends Controller
{
    public static string $resource = TenantTicketResource::class;
}
