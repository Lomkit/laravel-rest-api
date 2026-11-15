<?php

namespace Lomkit\Rest\Tests\Feature\Authorization\Stubs;

use Lomkit\Rest\Authorization\PendingChecks;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TagPolicy;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TagResource;

class PreparedTagResource extends TagResource
{
    #[\Override]
    public function prepareAuthorization(PendingChecks $checks): void
    {
        app(TagPolicy::class)->calls[] = 'prepare '.$checks->ability.' '.implode(',', $checks->rows->modelKeys());
    }
}
