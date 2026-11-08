<?php

namespace Lomkit\Rest\Tests\Feature\Metrics\Stubs;

use Lomkit\Rest\Http\Controllers\Controller;

class TenantOrderController extends Controller
{
    public static string $resource = TenantOrderResource::class;
}
