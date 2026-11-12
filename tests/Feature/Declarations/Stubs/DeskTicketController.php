<?php

namespace Lomkit\Rest\Tests\Feature\Declarations\Stubs;

use Lomkit\Rest\Http\Controllers\Controller;

class DeskTicketController extends Controller
{
    public static string $resource = DeskTicketResource::class;
}
