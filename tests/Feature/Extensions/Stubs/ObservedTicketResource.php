<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Lomkit\Rest\Tests\Feature\Events\Stubs\EventTicketResource;

final class ObservedTicketResource extends EventTicketResource
{
    #[\Override]
    public function metrics(): array
    {
        return [OpenTicketCount::make()->cacheFor(600)];
    }

    #[\Override]
    public function detailsMeta(): array
    {
        return ['plan' => 'pro'];
    }
}
