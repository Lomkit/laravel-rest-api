<?php

namespace Lomkit\Rest\Tests\Fixtures\Ledger;

use Lomkit\Rest\Http\Controllers\Controller;

class ScopedVoucherController extends Controller
{
    public static string $resource = ScopedVoucherResource::class;
}
