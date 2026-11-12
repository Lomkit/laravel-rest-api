<?php

namespace Lomkit\Rest\Tests\Feature\Declarations\Stubs;

use Lomkit\Rest\Http\Controllers\Controller;

class OtherDeskTicketController extends Controller
{
    public static string $resource = DeskTicketResource::class;
}
