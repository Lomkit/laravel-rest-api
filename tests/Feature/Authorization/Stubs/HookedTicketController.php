<?php

namespace Lomkit\Rest\Tests\Feature\Authorization\Stubs;

use Lomkit\Rest\Http\Requests\DetailsRequest;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketController;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketPolicy;

class HookedTicketController extends TicketController
{
    protected function beforeDetails(DetailsRequest $request): void
    {
        app(TicketPolicy::class)->calls[] = 'beforeDetails';
    }
}
