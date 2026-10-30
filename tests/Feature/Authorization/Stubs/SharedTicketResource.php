<?php

namespace Lomkit\Rest\Tests\Feature\Authorization\Stubs;

use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Routing\Endpoint;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketResource;

class SharedTicketResource extends TicketResource
{
    public function abilities(Endpoint $endpoint, RestRequest $request): array
    {
        return $endpoint === Endpoint::Search ? [] : parent::abilities($endpoint, $request);
    }
}
