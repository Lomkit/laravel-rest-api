<?php

namespace Lomkit\Rest\Tests\Feature\Metrics\Stubs;

use Lomkit\Rest\Relations\BelongsToMany;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketResource;

class RevealedTagTicketResource extends TicketResource
{
    #[\Override]
    public function relations(): array
    {
        return [BelongsToMany::make('tags', RevealedTagResource::class)];
    }

    #[\Override]
    public function metrics(): array
    {
        return [
            TicketBacklog::make()->cacheFor(600),
            RowCount::make()
                ->cacheFor(600)
                ->cacheScope('global'),
        ];
    }
}
