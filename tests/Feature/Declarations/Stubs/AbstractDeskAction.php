<?php

namespace Lomkit\Rest\Tests\Feature\Declarations\Stubs;

use Lomkit\Rest\Tests\Fixtures\Helpdesk\CloseTickets;

abstract class AbstractDeskAction extends CloseTickets
{
    public ?string $name = 'Abstract Desk';
}
