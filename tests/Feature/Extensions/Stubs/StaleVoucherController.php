<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Lomkit\Rest\Http\Controllers\Controller;

class StaleVoucherController extends Controller
{
    public static string $resource = StaleVoucherResource::class;
}
