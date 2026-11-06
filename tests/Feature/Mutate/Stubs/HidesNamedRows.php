<?php

namespace Lomkit\Rest\Tests\Feature\Mutate\Stubs;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Lomkit\Rest\Http\Requests\RestRequest;

trait HidesNamedRows
{
    abstract protected static function nameColumn(): string;

    #[\Override]
    public function searchQuery(RestRequest $request, Builder $query): Builder
    {
        return $query->whereNotIn(static::nameColumn(), ['Hidden by search', 'Hidden by both']);
    }

    #[\Override]
    public function mutateQuery(RestRequest $request, Builder $query): Builder
    {
        return $this->hideWritten($query);
    }

    #[\Override]
    public function destroyQuery(RestRequest $request, Builder $query): Builder
    {
        return $this->hideWritten($query);
    }

    #[\Override]
    public function restoreQuery(RestRequest $request, Builder $query): Builder
    {
        return $this->hideWritten($query);
    }

    #[\Override]
    public function forceDestroyQuery(RestRequest $request, Builder $query): Builder
    {
        return $this->hideWritten($query);
    }

    private function hideWritten(Builder $query): Builder
    {
        return $query->whereNotIn(static::nameColumn(), ['Hidden by write', 'Hidden by both']);
    }
}
