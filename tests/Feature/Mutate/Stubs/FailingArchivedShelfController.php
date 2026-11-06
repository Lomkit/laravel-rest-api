<?php

namespace Lomkit\Rest\Tests\Feature\Mutate\Stubs;

use Lomkit\Rest\Http\Controllers\Controller;

class FailingArchivedShelfController extends Controller
{
    public static string $resource = FailingArchivedShelfResource::class;
}
