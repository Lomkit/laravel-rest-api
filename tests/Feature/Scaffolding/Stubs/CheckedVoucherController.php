<?php

namespace Lomkit\Rest\Tests\Feature\Scaffolding\Stubs;

use Lomkit\Rest\Http\Controllers\Controller;

final class CheckedVoucherController extends Controller
{
    public static string $resource = CheckedVoucherResource::class;
}
