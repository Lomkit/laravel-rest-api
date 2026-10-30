<?php

namespace Lomkit\Rest\Tests\Feature\Relations\Stubs;

use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Relations\MorphedByMany;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TagResource;

class WeightedTagResource extends TagResource
{
    #[\Override]
    public function relations(RestRequest $request): array
    {
        return [MorphedByMany::make('labelledTickets', WeightedTicketResource::class)->pivotFields(WeightedPivot::fields())];
    }
}
