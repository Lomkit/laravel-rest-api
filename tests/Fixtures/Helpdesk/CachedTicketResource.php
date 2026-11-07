<?php

namespace Lomkit\Rest\Tests\Fixtures\Helpdesk;

class CachedTicketResource extends TicketResource
{
    #[\Override]
    public function authorizationCache(): array
    {
        return ['viewAny' => 300, 'view' => 60, 'create' => 300, 'update' => 300, 'delete' => 300, 'attachTag' => 300];
    }
}
