<?php

namespace Lomkit\Rest\Tests\Feature\RequestLifecycle\Stubs;

use Lomkit\Rest\Tests\Fixtures\Publishing\PostResource;

class StaffNotedPostResource extends PostResource
{
    #[\Override]
    public function actions(): array
    {
        return [StaffNotedPublishPosts::make()];
    }
}
