<?php

namespace Lomkit\Rest\Tests\Feature\Events\Stubs;

use Lomkit\Rest\Http\Controllers\Controller;

class HookDocumentController extends Controller
{
    public static string $resource = HookDocumentResource::class;
}
