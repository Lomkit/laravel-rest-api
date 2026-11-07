<?php

namespace Lomkit\Rest\Tests\Feature\Actions\Stubs;

use Lomkit\Rest\Http\Controllers\Controller;

class RenamedSecondaryDocumentController extends Controller
{
    public static string $resource = RenamedSecondaryDocumentResource::class;
}
