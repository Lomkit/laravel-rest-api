<?php

namespace Lomkit\Rest\Tests\Feature\Authorization\Stubs;

use Lomkit\Rest\Tests\Fixtures\Helpdesk\CachedTicketResource;
use Lomkit\Rest\Tests\Fixtures\Tenancy\Tenancy;

class TenantTicketResource extends CachedTicketResource
{
    #[\Override]
    public function authorizationCacheContext(): array
    {
        return ['tenant' => Tenancy::$tenant];
    }
}
