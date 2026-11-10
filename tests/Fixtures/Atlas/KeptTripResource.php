<?php

namespace Lomkit\Rest\Tests\Fixtures\Atlas;

use Lomkit\Rest\Fields\MutationOperation;
use Lomkit\Rest\Relations\BelongsTo;

class KeptTripResource extends TripResource
{
    #[\Override]
    public function relations(): array
    {
        return [BelongsTo::make('country', CountryResource::class)->withoutOperations(MutationOperation::Detach)];
    }
}
