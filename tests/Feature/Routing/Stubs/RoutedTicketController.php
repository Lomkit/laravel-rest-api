<?php

namespace Lomkit\Rest\Tests\Feature\Routing\Stubs;

use Lomkit\Rest\Http\Controllers\Controller;

class RoutedTicketController extends Controller
{
    public static string $resource = RoutedTicketResource::class;
}
