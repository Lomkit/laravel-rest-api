<?php

namespace Lomkit\Rest\Tests\Feature\Metrics\Stubs;

use Lomkit\Rest\Relations\BelongsToMany;
use Lomkit\Rest\Relations\MorphToMany;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketResource;

class TaggedTicketResource extends TicketResource
{
    #[\Override]
    public function relations(): array
    {
        return [
            BelongsToMany::make('tags', PrivateTagResource::class),
            MorphToMany::make('labels', PrivateTagResource::class),
        ];
    }

    #[\Override]
    public function metrics(): array
    {
        return [
            TicketBacklog::make()
                ->cacheFor(600)
                ->cacheScope('global'),
        ];
    }
}
