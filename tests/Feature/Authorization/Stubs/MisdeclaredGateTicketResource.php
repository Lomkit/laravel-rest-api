<?php

namespace Lomkit\Rest\Tests\Feature\Authorization\Stubs;

use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketResource;

class MisdeclaredGateTicketResource extends TicketResource
{
    #[\Override]
    public function gates(RestRequest $request): array
    {
        return ['escalate', 'view'];
    }
}
