<?php

namespace Lomkit\Rest\Text\Engine;

use Illuminate\Database\Eloquent\Builder;

final class TrackedRows implements RowRestriction
{
    private bool $applied = false;

    public function __construct(private readonly RowRestriction $rows) {}

    #[\Override]
    public function apply(Builder $query): void
    {
        $this->rows->apply($query);
        $this->applied = true;
    }

    public function applied(): bool
    {
        return $this->applied;
    }
}
