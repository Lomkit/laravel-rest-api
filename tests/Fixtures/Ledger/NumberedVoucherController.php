<?php

namespace Lomkit\Rest\Tests\Fixtures\Ledger;

use Lomkit\Rest\Http\Controllers\Controller;

class NumberedVoucherController extends Controller
{
    public static string $resource = NumberedVoucherResource::class;
}
