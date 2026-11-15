<?php

namespace Lomkit\Rest\Tests\Feature\Authorization\Stubs;

use Lomkit\Rest\Http\Controllers\Controller;

class VersionedTicketController extends Controller
{
    public static string $resource = VersionedTicketResource::class;
}
