<?php

namespace Lomkit\Rest\Tests\Feature\Search\Totals\Stubs;

use Lomkit\Rest\Http\Controllers\Controller;

class CappedShowController extends Controller
{
    public static string $resource = CappedShowResource::class;
}
