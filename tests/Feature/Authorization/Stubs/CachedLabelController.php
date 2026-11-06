<?php

namespace Lomkit\Rest\Tests\Feature\Authorization\Stubs;

use Lomkit\Rest\Http\Controllers\Controller;

class CachedLabelController extends Controller
{
    public static string $resource = CachedLabelResource::class;
}
