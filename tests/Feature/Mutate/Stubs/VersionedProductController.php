<?php

namespace Lomkit\Rest\Tests\Feature\Mutate\Stubs;

use Lomkit\Rest\Http\Controllers\Controller;

class VersionedProductController extends Controller
{
    public static string $resource = VersionedProductResource::class;
}
