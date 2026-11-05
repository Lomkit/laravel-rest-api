<?php

namespace Lomkit\Rest\Tests\Feature\Search\Stubs;

use Lomkit\Rest\Tests\Fixtures\Library\BookResource;

class TalliedBookResource extends BookResource
{
    public static int $reads = 0;

    #[\Override]
    public function fields(): array
    {
        self::$reads++;

        return parent::fields();
    }
}
