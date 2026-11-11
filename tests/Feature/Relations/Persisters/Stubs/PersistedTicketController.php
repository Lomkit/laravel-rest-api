<?php

namespace Lomkit\Rest\Tests\Feature\Relations\Persisters\Stubs;

use Lomkit\Rest\Http\Controllers\Controller;

class PersistedTicketController extends Controller
{
    public static string $resource = PersistedTicketResource::class;
}
