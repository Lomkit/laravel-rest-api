<?php

namespace Lomkit\Rest\Tests\Feature\Actions\Stubs;

use Illuminate\Contracts\Queue\ShouldQueue;

class QueuedClosureRuledPublishPosts extends ClosureRuledPublishPosts implements ShouldQueue {}
