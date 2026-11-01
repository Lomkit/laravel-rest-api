<?php

namespace Lomkit\Rest\Tests\Feature\Actions\Stubs;

class StandaloneCountedPublishPosts extends CountedPublishPosts
{
    public bool $standalone = true;
}
