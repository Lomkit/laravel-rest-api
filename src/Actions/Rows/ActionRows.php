<?php

namespace Lomkit\Rest\Actions\Rows;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

interface ActionRows
{
    /** @param Closure(Collection<int, Model>): int $each */
    public function each(Closure $each): int;

    public function count(): int;

    public function only(CheckedKeys $keys): ActionRows;
}
