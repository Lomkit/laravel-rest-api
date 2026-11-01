<?php

namespace Lomkit\Rest\Tests\Feature\Actions\Stubs;

class TargetedCountedPublishPosts extends CountedPublishPosts
{
    public bool $targeted = true;
}
