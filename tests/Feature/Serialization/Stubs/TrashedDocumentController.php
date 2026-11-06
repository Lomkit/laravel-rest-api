<?php

namespace Lomkit\Rest\Tests\Feature\Serialization\Stubs;

use Lomkit\Rest\Http\Controllers\Controller;

class TrashedDocumentController extends Controller
{
    public static string $resource = TrashedDocumentResource::class;
}
