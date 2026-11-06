<?php

namespace Lomkit\Rest\Tests\Feature\Mutate\Stubs;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Lomkit\Rest\Http\Requests\RestRequest;

trait HidesNamedRows
{
    abstract protected static function nameColumn(): string;

    #[\Override]
    public function perimeterQuery(RestRequest $request, Builder $query): Builder
    {
        return $query->where(static::nameColumn(), '<>', 'Hidden');
    }
}
