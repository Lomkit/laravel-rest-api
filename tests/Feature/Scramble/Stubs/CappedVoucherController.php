<?php

namespace Lomkit\Rest\Tests\Feature\Scramble\Stubs;

use Lomkit\Rest\Http\Controllers\Controller;

class CappedVoucherController extends Controller
{
    public static string $resource = CappedVoucherResource::class;
}
