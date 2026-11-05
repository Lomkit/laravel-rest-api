<?php

namespace Lomkit\Rest\Tests\Feature\Details\Stubs;

use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TeamResource;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketResource;

class TeamPivotTicketResource extends TicketResource
{
    #[\Override]
    public function relations(): array
    {
        return [PivotBelongsTo::make('team', TeamResource::class)->pivotFields([Integer::make('position')->nullable()])];
    }
}
