<?php

namespace Lomkit\Rest\Tests\Feature\Events\Stubs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Lomkit\Rest\Events\ActionOperated;
use Lomkit\Rest\Events\ResourceDestroyed;
use Lomkit\Rest\Events\ResourceForceDestroyed;
use Lomkit\Rest\Events\ResourceMutated;
use Lomkit\Rest\Events\ResourceRestored;

final class QueuedWriteListener implements ShouldQueue
{
    /** @var list<ResourceMutated|ResourceDestroyed|ResourceRestored|ResourceForceDestroyed|ActionOperated> */
    public static array $events = [];

    public function handle(ResourceMutated|ResourceDestroyed|ResourceRestored|ResourceForceDestroyed|ActionOperated $event): void
    {
        self::$events[] = $event;
    }
}
