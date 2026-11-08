<?php

namespace Lomkit\Rest\Tests\Feature\Authorization\Stubs;

use Lomkit\Rest\Http\Controllers\Controller;

class UngatedShelfLabelController extends Controller
{
    public static string $resource = UngatedShelfLabelResource::class;
}
