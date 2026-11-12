<?php

namespace Lomkit\Rest\Tests\Feature\Declarations\Stubs;

use Lomkit\Rest\Tests\Fixtures\Helpdesk\RefreshTickets;

class DeskRefreshTickets extends RefreshTickets
{
    public ?string $name = 'Refresh Tickets';

    public function __construct()
    {
        DeclarationLog::$built[] = static::class;
    }
}
