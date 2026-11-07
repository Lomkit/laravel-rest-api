<?php

namespace Lomkit\Rest\Tests\Feature\Actions\Stubs;

use Lomkit\Rest\Actions\ActionTarget;

class StandaloneCountedPublishPosts extends CountedPublishPosts
{
    public ActionTarget $target = ActionTarget::None;
}
