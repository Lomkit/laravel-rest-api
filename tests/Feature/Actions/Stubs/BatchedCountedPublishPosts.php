<?php

namespace Lomkit\Rest\Tests\Feature\Actions\Stubs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Lomkit\Rest\Contracts\BatchableAction;

class BatchedCountedPublishPosts extends CountedPublishPosts implements BatchableAction, ShouldQueue {}
