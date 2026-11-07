<?php

namespace Lomkit\Rest\Actions\Rows;

use Closure;
use Illuminate\Database\Eloquent\Collection;

final readonly class NoRows implements ActionRows
{
    #[\Override]
    public function each(Closure $each): int
    {
        return $each(new Collection);
    }
}
