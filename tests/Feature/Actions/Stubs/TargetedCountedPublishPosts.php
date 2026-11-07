<?php

namespace Lomkit\Rest\Tests\Feature\Actions\Stubs;

use Lomkit\Rest\Actions\ActionTarget;

class TargetedCountedPublishPosts extends CountedPublishPosts
{
    public ActionTarget $target = ActionTarget::Resources;
}
