<?php

namespace Lomkit\Rest\Tests\Feature\Documentation\Stubs;

use Lomkit\Rest\Http\Controllers\Controller;

class CappedStageController extends Controller
{
    public static string $resource = CappedStageResource::class;
}
