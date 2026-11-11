<?php

namespace Lomkit\Rest\Tests\Feature\Relations\Persisters\Stubs;

use Lomkit\Rest\Contracts\PivotPersister;
use Lomkit\Rest\Mutations\EloquentPivots;
use Lomkit\Rest\Mutations\PivotWrite;

class RecordingPivots implements PivotPersister
{
    /** @var list<string> */
    public static array $calls = [];

    #[\Override]
    public function attach(PivotWrite $write): void
    {
        self::record('attach', $write);
        (new EloquentPivots)->attach($write);
    }

    #[\Override]
    public function detach(PivotWrite $write): void
    {
        self::record('detach', $write);
        (new EloquentPivots)->detach($write);
    }

    #[\Override]
    public function updatePivot(PivotWrite $write): void
    {
        self::record('updatePivot', $write);
        (new EloquentPivots)->updatePivot($write);
    }

    private static function record(string $primitive, PivotWrite $write): void
    {
        self::$calls[] = $primitive.' ['.implode(', ', $write->keys()).'] '.json_encode($write->pivot, JSON_THROW_ON_ERROR);
    }
}
