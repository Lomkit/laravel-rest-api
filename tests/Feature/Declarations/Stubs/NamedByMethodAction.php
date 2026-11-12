<?php

namespace Lomkit\Rest\Tests\Feature\Declarations\Stubs;

use Lomkit\Rest\Tests\Fixtures\Helpdesk\RefreshTickets;

class NamedByMethodAction extends RefreshTickets
{
    #[\Override]
    public function name(): string
    {
        return 'Refresh '.date('Y');
    }
}
