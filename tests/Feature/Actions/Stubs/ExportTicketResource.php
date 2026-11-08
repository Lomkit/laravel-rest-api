<?php

namespace Lomkit\Rest\Tests\Feature\Actions\Stubs;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketResource;

class ExportTicketResource extends TicketResource
{
    #[\Override]
    public function perimeterQuery(Builder $query): Builder
    {
        return $query->where('team_id', 1);
    }

    #[\Override]
    public function actions(): array
    {
        $export = ExportTickets::make();
        $export->chunkCount = 2;

        return [$export];
    }
}
