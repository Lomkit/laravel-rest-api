<?php

namespace Lomkit\Rest\Actions\Rows;

use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/** @internal */
final readonly class NoRows implements ActionRows
{
    #[\Override]
    public function each(Closure $each): int
    {
        return $each(new Collection);
    }

    #[\Override]
    public function count(): int
    {
        return 0;
    }

    #[\Override]
    public function keyOf(Model $model): int|string|null
    {
        return null;
    }

    #[\Override]
    public function only(CheckedKeys $keys): ActionRows
    {
        return $this;
    }
}
