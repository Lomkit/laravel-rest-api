<?php

namespace Lomkit\Rest\Query\Clauses;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use InvalidArgumentException;
use Lomkit\Rest\Text\QueryTextSearch;
use Lomkit\Rest\Text\TextQuery;
use Lomkit\Rest\Text\TextTarget;

final readonly class TextClause implements Clause
{
    public function __construct(
        private QueryTextSearch $strategy,
        private TextQuery $text,
        private TextTarget $target,
    ) {}

    #[\Override]
    public function apply(Builder $query): void
    {
        if (! $query instanceof EloquentBuilder) {
            throw new InvalidArgumentException('A text search applies to the Eloquent builder of the searched resource, not to '.get_debug_type($query).'.');
        }

        $query->where(function (EloquentBuilder $query): void {
            $this->strategy->apply($query, $this->text, $this->target);
        });
    }
}
