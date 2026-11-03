<?php

namespace Lomkit\Rest\Tests\Feature\Serialization\Stubs;

use Lomkit\Rest\Http\Controllers\Controller;

class UnrelatedEagerLoanController extends Controller
{
    public static string $resource = UnrelatedEagerLoanResource::class;
}
