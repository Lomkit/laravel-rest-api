<?php

namespace Lomkit\Rest\Tests\Feature\Routing\Stubs;

use Lomkit\Rest\Tests\Fixtures\Helpdesk\WatchTickets;

class ThrottledWatchTickets extends WatchTickets
{
    public array $middleware = ['throttle:60,1'];

    public array $withoutMiddleware = ['verified'];
}
