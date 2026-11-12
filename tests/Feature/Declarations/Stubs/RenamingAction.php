<?php

namespace Lomkit\Rest\Tests\Feature\Declarations\Stubs;

use Lomkit\Rest\Tests\Fixtures\Helpdesk\CloseTickets;

class RenamingAction extends CloseTickets
{
    public function __construct()
    {
        DeclarationLog::$built[] = static::class;
        $this->name = 'Close Everything';
    }
}
