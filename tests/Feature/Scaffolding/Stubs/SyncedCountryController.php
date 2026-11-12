<?php

namespace Lomkit\Rest\Tests\Feature\Scaffolding\Stubs;

use Lomkit\Rest\Http\Controllers\Controller;

class SyncedCountryController extends Controller
{
    public static string $resource = SyncedCountryResource::class;
}
