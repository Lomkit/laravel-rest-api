<?php

namespace Lomkit\Rest\Tests\Feature\Relations\Persisters\Stubs;

use Lomkit\Rest\Http\Controllers\Controller;

class PersistedTagController extends Controller
{
    public static string $resource = PersistedTagResource::class;
}
