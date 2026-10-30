<?php

namespace Lomkit\Rest\Tests\Feature\Authorization\Stubs;

use Lomkit\Rest\Http\Controllers\Controller;

class LockedTeamTicketController extends Controller
{
    public static string $resource = LockedTeamTicketResource::class;
}
