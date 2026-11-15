<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Lomkit\Rest\Tests\Fixtures\Helpdesk\CachedTicketResource;

class DottedContextTicketResource extends CachedTicketResource
{
    #[\Override]
    public function authorizationCacheContext(): array
    {
        return ['acl.rules' => 1];
    }
}
