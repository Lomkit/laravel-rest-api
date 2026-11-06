<?php

namespace Lomkit\Rest\Tests\Feature\Deletion\Stubs;

use Lomkit\Rest\Http\Controllers\Controller;

class FailingSecondaryDocumentController extends Controller
{
    public static string $resource = FailingSecondaryDocumentResource::class;
}
