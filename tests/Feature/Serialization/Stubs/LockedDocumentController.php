<?php

namespace Lomkit\Rest\Tests\Feature\Serialization\Stubs;

use Lomkit\Rest\Http\Controllers\Controller;

class LockedDocumentController extends Controller
{
    public static string $resource = LockedDocumentResource::class;
}
