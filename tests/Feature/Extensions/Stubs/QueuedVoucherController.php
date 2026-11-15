<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Lomkit\Rest\Http\Controllers\Controller;

class QueuedVoucherController extends Controller
{
    public static string $resource = QueuedVoucherResource::class;
}
