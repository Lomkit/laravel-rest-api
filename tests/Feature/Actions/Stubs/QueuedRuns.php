<?php

namespace Lomkit\Rest\Tests\Feature\Actions\Stubs;

final class QueuedRuns
{
    /** @var list<array<string, mixed>> */
    public static array $runs = [];

    public static function reset(): void
    {
        self::$runs = [];
        StaffDocumentResource::$exports = true;
    }
}
