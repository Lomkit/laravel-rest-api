<?php

namespace Lomkit\Rest\Tests\Unit\Search\Stubs;

use Lomkit\Rest\Search\FilterNode;

final readonly class UnknownFilterNode implements FilterNode
{
    #[\Override]
    public function boolean(): string
    {
        return 'and';
    }
}
