<?php

namespace Lomkit\Rest\Tests\Feature\Authorization\Stubs;

use Lomkit\Rest\Http\Controllers\Controller;

class PreparedTicketController extends Controller
{
    public static string $resource = PreparedTicketResource::class;
}
