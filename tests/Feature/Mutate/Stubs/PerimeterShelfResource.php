<?php

namespace Lomkit\Rest\Tests\Feature\Mutate\Stubs;

use Lomkit\Rest\Tests\Fixtures\Library\ShelfResource;

class PerimeterShelfResource extends ShelfResource
{
    use HidesNamedRows;

    protected static function nameColumn(): string
    {
        return 'shelves.name';
    }
}
