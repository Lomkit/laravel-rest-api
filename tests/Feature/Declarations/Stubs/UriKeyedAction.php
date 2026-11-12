<?php

namespace Lomkit\Rest\Tests\Feature\Declarations\Stubs;

use Lomkit\Rest\Tests\Fixtures\Helpdesk\RefreshTickets;

class UriKeyedAction extends RefreshTickets
{
    #[\Override]
    public function uriKey(): string
    {
        return 'refresh';
    }
}
