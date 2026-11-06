<?php

namespace Lomkit\Rest\Tests\Feature\Deletion\Stubs;

use Lomkit\Rest\Tests\Feature\Mutate\Stubs\HidesNamedRows;
use Lomkit\Rest\Tests\Fixtures\Archive\DocumentResource;

class PerimeterDocumentResource extends DocumentResource
{
    use HidesNamedRows;

    protected static function nameColumn(): string
    {
        return 'documents.title';
    }
}
