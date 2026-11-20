<?php

namespace Lomkit\Rest\Tests\Feature\Search\Totals\Stubs;

use Lomkit\Rest\Tests\Fixtures\Festival\ShowResource;

class CappedShowResource extends ShowResource
{
    public ?int $maxTotal = 100;
}
