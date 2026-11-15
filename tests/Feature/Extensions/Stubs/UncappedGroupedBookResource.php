<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Lomkit\Rest\Tests\Feature\Search\Stubs\CappedBookResource;

class UncappedGroupedBookResource extends CappedBookResource
{
    public ?int $maxTotal = null;
}
