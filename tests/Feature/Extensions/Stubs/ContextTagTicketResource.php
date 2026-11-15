<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Lomkit\Rest\Relations\BelongsToMany;
use Lomkit\Rest\Tests\Feature\Metrics\Stubs\RowCount;
use Lomkit\Rest\Tests\Feature\Metrics\Stubs\TicketBacklog;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TagResource;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketResource;

class ContextTagTicketResource extends TicketResource
{
    #[\Override]
    public function relations(): array
    {
        return [BelongsToMany::make('tags', TagResource::class)];
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
