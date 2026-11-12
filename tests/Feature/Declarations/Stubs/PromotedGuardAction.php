<?php

namespace Lomkit\Rest\Tests\Feature\Declarations\Stubs;

use Lomkit\Rest\Tests\Fixtures\Helpdesk\CloseTickets;

class PromotedGuardAction extends CloseTickets
{
    /** @param list<string> $middleware */
    public function __construct(public array $middleware = ['can:admin'])
    {
        DeclarationLog::$built[] = static::class;
    }
}
