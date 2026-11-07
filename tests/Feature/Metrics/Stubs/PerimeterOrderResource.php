<?php

namespace Lomkit\Rest\Tests\Feature\Metrics\Stubs;

class PerimeterOrderResource extends TeamOrderResource
{
    #[\Override]
    public function authorizationCacheContext(): array
    {
        return [];
    }
}
