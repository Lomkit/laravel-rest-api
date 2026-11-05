<?php

namespace Lomkit\Rest\Tests\Feature\MorphTargets\Stubs;

use Lomkit\Rest\Http\Controllers\Controller;

class DeclaredLabelController extends Controller
{
    public static string $resource = DeclaredLabelResource::class;
}
