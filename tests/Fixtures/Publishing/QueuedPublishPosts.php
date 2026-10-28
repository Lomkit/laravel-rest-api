<?php

namespace Lomkit\Rest\Tests\Fixtures\Publishing;

use Illuminate\Contracts\Queue\ShouldQueue;

class QueuedPublishPosts extends PublishPosts implements ShouldQueue {}
