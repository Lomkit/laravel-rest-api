<?php

namespace Lomkit\Rest\Tests\Feature\Authorization\Stubs;

use Lomkit\Rest\Http\Controllers\Controller;

class SealedTicketController extends Controller
{
    public static string $resource = SealedTicketResource::class;
}
