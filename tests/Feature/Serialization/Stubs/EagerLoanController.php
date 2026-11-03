<?php

namespace Lomkit\Rest\Tests\Feature\Serialization\Stubs;

use Lomkit\Rest\Http\Controllers\Controller;

class EagerLoanController extends Controller
{
    public static string $resource = EagerLoanResource::class;
}
