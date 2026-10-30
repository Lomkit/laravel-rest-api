<?php

namespace Lomkit\Rest\Tests\Feature\Authorization\Stubs;

use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Routing\Endpoint;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketResource;

class LeadTicketResource extends TicketResource
{
    public function abilities(Endpoint $endpoint, RestRequest $request): array
    {
        return match ($endpoint) {
            Endpoint::Mutate => ['updateAny'],
            Endpoint::Operate => ['runActions'],
            Endpoint::Destroy => ['deleteAny'],
            Endpoint::Restore => ['restoreAny'],
            Endpoint::ForceDestroy => ['forceDeleteAny'],
            default => parent::abilities($endpoint, $request),
        };
    }
}
