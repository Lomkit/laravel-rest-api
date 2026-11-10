<?php

namespace Lomkit\Rest\Tests\Feature\Authorization\Stubs;

use Lomkit\Rest\Http\Controllers\Controller;

class LockedTicketsTagController extends Controller
{
    public static string $resource = LockedTicketsTagResource::class;
}
