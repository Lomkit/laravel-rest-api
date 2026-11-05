<?php

namespace Lomkit\Rest\Tests\Feature\Serialization\Stubs;

class UnrelatedEagerLoanResource extends EagerLoanResource
{
    #[\Override]
    public function relations(): array
    {
        return [];
    }
}
