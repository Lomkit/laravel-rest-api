<?php

namespace Lomkit\Rest\Tests\Feature\Details\Stubs;

use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TeamResource;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketResource;

class TeamPivotTicketResource extends TicketResource
{
    #[\Override]
    public function relations(RestRequest $request): array
    {
        return [PivotBelongsTo::make('team', TeamResource::class)->pivotFields([Integer::make('position')->nullable()])];
    }
}
