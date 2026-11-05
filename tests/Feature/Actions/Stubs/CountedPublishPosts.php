<?php

namespace Lomkit\Rest\Tests\Feature\Actions\Stubs;

use Lomkit\Rest\Tests\Fixtures\Publishing\PublishPosts;

class CountedPublishPosts extends PublishPosts
{
    public static int $calls = 0;

    #[\Override]
    public function fields(): array
    {
        CountedPublishPosts::$calls++;

        return parent::fields();
    }
}
