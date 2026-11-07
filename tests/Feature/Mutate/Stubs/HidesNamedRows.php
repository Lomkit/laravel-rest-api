<?php

namespace Lomkit\Rest\Tests\Feature\Mutate\Stubs;

use Illuminate\Contracts\Database\Eloquent\Builder;

trait HidesNamedRows
{
    abstract protected static function nameColumn(): string;

    #[\Override]
    public function perimeterQuery(Builder $query): Builder
    {
        return $query->where(static::nameColumn(), '<>', 'Hidden');
    }
}
