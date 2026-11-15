<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Lomkit\Rest\Http\Controllers\Controller;

final class MetaTicketController extends Controller
{
    public static string $resource = MetaTicketResource::class;
}
