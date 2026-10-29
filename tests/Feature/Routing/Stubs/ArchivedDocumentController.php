<?php

namespace Lomkit\Rest\Tests\Feature\Routing\Stubs;

use Illuminate\Routing\Controllers\Middleware;
use Lomkit\Rest\Tests\Fixtures\Archive\DocumentResource;

class ArchivedDocumentController extends BaseController
{
    public static string $resource = DocumentResource::class;

    public static function middleware(): array
    {
        return [
            ...parent::middleware(),
            new Middleware('can:archive', only: ['destroy']),
        ];
    }
}
