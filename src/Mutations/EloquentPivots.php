<?php

namespace Lomkit\Rest\Mutations;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Lomkit\Rest\Contracts\PivotPersister;

final readonly class EloquentPivots implements PivotPersister
{
    #[\Override]
    public function attach(PivotWrite $write): void
    {
        $pivots = $write->pivots();
        $keys = $write->keys();
        $linked = self::created($write) ? [] : LinkedKeys::among($pivots, $keys);
        $missing = LinkedKeys::missing($keys, $linked);

        if ($missing !== []) {
            $pivots->attach(array_fill_keys($missing, $write->pivot));
        }

        if ($linked !== [] && $write->pivot !== []) {
            self::update($pivots, $linked, $write->pivot);
        }
    }

    #[\Override]
    public function detach(PivotWrite $write): void
    {
        $write
            ->pivots()
            ->detach($write->related);
    }

    #[\Override]
    public function updatePivot(PivotWrite $write): void
    {
        self::update($write->pivots(), $write->keys(), $write->pivot);
    }

    private static function created(PivotWrite $write): bool
    {
        if ($write->parent->wasRecentlyCreated) {
            return true;
        }

        return array_filter($write->related, fn (Model $row): bool => ! $row->wasRecentlyCreated) === [];
    }

    /**
     * @param  BelongsToMany<Model, Model>  $pivots
     * @param  list<int|string>  $keys
     * @param  array<string, mixed>  $pivot
     */
    private static function update(BelongsToMany $pivots, array $keys, array $pivot): void
    {
        $updated = 0;

        foreach ($keys as $key) {
            $updated += $pivots->updateExistingPivot($key, $pivot, false);
        }

        if ($updated > 0) {
            $pivots->touchIfTouching();
        }
    }
}
