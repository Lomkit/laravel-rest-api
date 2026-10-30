<?php

namespace Lomkit\Rest\Tests\Feature\Routing\Stubs;

use Lomkit\Rest\Tests\Fixtures\Archive\DocumentResource;

class AuthenticatedDocumentController extends BaseController
{
    public static string $resource = DocumentResource::class;
}
