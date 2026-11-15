<?php

namespace Lomkit\Rest\Tests\Feature\Scaffolding\Stubs;

use Lomkit\Rest\Http\Controllers\Controller;

final class RouteBoundVoucherController extends Controller
{
    public static string $resource = RouteBoundVoucherResource::class;
}
