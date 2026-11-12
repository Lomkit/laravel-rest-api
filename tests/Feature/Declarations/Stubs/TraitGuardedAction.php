<?php

namespace Lomkit\Rest\Tests\Feature\Declarations\Stubs;

use Lomkit\Rest\Tests\Fixtures\Helpdesk\CloseTickets;

class TraitGuardedAction extends CloseTickets
{
    use AdminOnly;
}
