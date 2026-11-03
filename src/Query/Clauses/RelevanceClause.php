<?php

namespace Lomkit\Rest\Query\Clauses;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use InvalidArgumentException;
use Lomkit\Rest\Fields\SortDirection;
use Lomkit\Rest\Text\QueryTextSearch;
use Lomkit\Rest\Text\TextQuery;
use Lomkit\Rest\Text\TextTarget;

final readonly class RelevanceClause implements Clause
{
    public const string FIELD = '@relevance';

    public function __construct(
        private QueryTextSearch $strategy,
        private TextQuery $text,
        private TextTarget $target,
        private SortDirection $direction = SortDirection::Desc,
    ) {}

    #[\Override]
    public function apply(Builder $query): void
    {
        if (! $query instanceof EloquentBuilder) {
            throw new InvalidArgumentException('A relevance order applies to the Eloquent builder of the searched resource, not to '.get_debug_type($query).'.');
        }

        $this->strategy->orderByRelevance($query, $this->text, $this->target, $this->direction);
    }
}
