<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Lomkit\Rest\Http\Controllers\Controller;

class MountedInstrumentController extends Controller
{
    public static string $resource = MountedInstrumentResource::class;
}
