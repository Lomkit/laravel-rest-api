<?php

namespace Lomkit\Rest\Tests\Feature\Serialization\Stubs;

use Lomkit\Rest\Http\Requests\RestRequest;

class UnrelatedEagerLoanResource extends EagerLoanResource
{
    #[\Override]
    public function relations(RestRequest $request): array
    {
        return [];
    }
}
