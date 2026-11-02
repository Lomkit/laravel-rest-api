<?php

namespace Lomkit\Rest\Tests\Feature\Actions\Stubs;

use Illuminate\Contracts\Queue\ShouldQueue;

class QueuedCountedPublishPosts extends CountedPublishPosts implements ShouldQueue {}
