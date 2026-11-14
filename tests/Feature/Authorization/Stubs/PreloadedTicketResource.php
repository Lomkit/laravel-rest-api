<?php

namespace Lomkit\Rest\Tests\Feature\Authorization\Stubs;

use Illuminate\Database\Eloquent\Collection;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketResource;

class PreloadedTicketResource extends TicketResource
{
    #[\Override]
    public function prepareAuthorization(string $ability, Collection $rows): void
    {
        $rows->loadMissing('team');
    }
}
