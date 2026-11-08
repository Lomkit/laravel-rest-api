<?php

namespace Lomkit\Rest\Tests\Feature\Transactions\Stubs;

use Lomkit\Rest\Http\Controllers\Controller;

class VersionedDocumentController extends Controller
{
    public static string $resource = VersionedDocumentResource::class;
}
