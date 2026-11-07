<?php

namespace Lomkit\Rest\Tests\Feature\Authorization\Stubs;

use Lomkit\Rest\Tests\Fixtures\Helpdesk\CachedTicketResource;

class BareTicketResource extends CachedTicketResource
{
    #[\Override]
    public function alwaysSelect(): array
    {
        return [];
    }
}
