<?php

namespace Lomkit\Rest\Tests\Feature\Routing\Stubs;

use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Lomkit\Rest\Http\Controllers\Controller;
use Lomkit\Rest\Tests\Fixtures\Archive\DocumentResource;

#[Middleware('throttle:5,1')]
#[Authorize('archive', only: ['destroy'])]
class GuardedDocumentController extends Controller
{
    public static string $resource = DocumentResource::class;
}
