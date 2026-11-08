<?php

namespace Lomkit\Rest\Tests\Feature\Transactions\Stubs;

use Lomkit\Rest\Http\Controllers\Controller;

class JournaledDocumentController extends Controller
{
    public static string $resource = JournaledDocumentResource::class;
}
