<?php

namespace Lomkit\Rest\Tests\Feature\Authorization\Stubs;

class CachedReviewedTicketResource extends ReviewedTicketResource
{
    #[\Override]
    public function authorizationCache(): array
    {
        return ['viewAny' => 300, 'update' => 300];
    }
}
