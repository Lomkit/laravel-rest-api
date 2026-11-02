<?php

namespace Lomkit\Rest\Tests\Unit\Search\Stubs;

use Lomkit\Rest\Fields\SortDirection;
use Lomkit\Rest\Search\SortNode;

final readonly class UnknownSortNode implements SortNode
{
    #[\Override]
    public function direction(): SortDirection
    {
        return SortDirection::Asc;
    }
}
