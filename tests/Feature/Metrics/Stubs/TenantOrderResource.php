<?php

namespace Lomkit\Rest\Tests\Feature\Metrics\Stubs;

use Lomkit\Rest\Tests\Fixtures\Tenancy\Tenancy;

class TenantOrderResource extends MetricOrderResource
{
    #[\Override]
    public function authorizationCacheContext(): array
    {
        return ['tenant' => Tenancy::$tenant];
    }
}
