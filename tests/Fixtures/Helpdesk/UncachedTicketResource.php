<?php

namespace Lomkit\Rest\Tests\Fixtures\Helpdesk;

use Lomkit\Rest\Concerns\Resource\DisableAuthorizationsCache;

class UncachedTicketResource extends CachedTicketResource
{
    use DisableAuthorizationsCache;
}
