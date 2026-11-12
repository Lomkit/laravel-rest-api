<?php

namespace Lomkit\Rest\Tests\Feature\Declarations\Stubs;

use Lomkit\Rest\Tests\Fixtures\Helpdesk\CloseTickets;

class SkippingAction extends CloseTickets
{
    public bool $skipUnauthorized = true;
}
