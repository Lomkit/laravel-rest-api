<?php

namespace Lomkit\Rest\Tests\Feature\Metrics\Stubs;

use Lomkit\Rest\Tests\Fixtures\Helpdesk\TagResource;

class RevealedTagResource extends TagResource
{
    #[\Override]
    public function authorizationCacheContext(): array
    {
        return ['reveal' => config()->boolean('helpdesk.reveal_secret_tags', false)];
    }
}
