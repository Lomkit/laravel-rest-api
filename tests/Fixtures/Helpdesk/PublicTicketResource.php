<?php

namespace Lomkit\Rest\Tests\Fixtures\Helpdesk;

use Lomkit\Rest\Concerns\Resource\DisableAuthorizations;

class PublicTicketResource extends TicketResource
{
    use DisableAuthorizations;
}
