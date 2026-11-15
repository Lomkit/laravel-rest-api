<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Lomkit\Rest\Http\Controllers\Controller;

final class WrittenDocumentController extends Controller
{
    public static string $resource = WrittenDocumentResource::class;
}
