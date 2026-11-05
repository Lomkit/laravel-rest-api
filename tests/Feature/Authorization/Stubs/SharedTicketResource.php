<?php

namespace Lomkit\Rest\Tests\Feature\Authorization\Stubs;

use Lomkit\Rest\Http\Routing\Endpoint;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketResource;

class SharedTicketResource extends TicketResource
{
    #[\Override]
    public function abilities(Endpoint $endpoint): array
    {
        return $endpoint === Endpoint::Search ? [] : parent::abilities($endpoint);
    }
}
