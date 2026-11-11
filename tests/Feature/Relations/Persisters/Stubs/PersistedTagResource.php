<?php

namespace Lomkit\Rest\Tests\Feature\Relations\Persisters\Stubs;

use Lomkit\Rest\Relations\MorphedByMany;
use Lomkit\Rest\Tests\Feature\Relations\Stubs\WeightedPivot;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TagResource;

class PersistedTagResource extends TagResource
{
    #[\Override]
    public function relations(): array
    {
        return [
            MorphedByMany::make('labelledTickets', PersistedTicketResource::class)
                ->pivotFields(WeightedPivot::fields())
                ->persistedBy(PersistedTicketResource::$persister),
        ];
    }
}
