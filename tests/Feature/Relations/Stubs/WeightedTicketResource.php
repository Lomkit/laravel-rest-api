<?php

namespace Lomkit\Rest\Tests\Feature\Relations\Stubs;

use Lomkit\Rest\Relations\BelongsToMany;
use Lomkit\Rest\Relations\MorphToMany;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketResource;

class WeightedTicketResource extends TicketResource
{
    #[\Override]
    public function relations(): array
    {
        return [
            BelongsToMany::make('tags', WeightedTagResource::class)->pivotFields(WeightedPivot::fields()),
            MorphToMany::make('labels', WeightedTagResource::class)->pivotFields(WeightedPivot::fields()),
        ];
    }
}
