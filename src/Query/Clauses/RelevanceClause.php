<?php

namespace Lomkit\Rest\Query\Clauses;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use InvalidArgumentException;
use Lomkit\Rest\Exceptions\InvalidFieldException;
use Lomkit\Rest\Fields\SortDirection;
use Lomkit\Rest\Text\QueryTextSearch;
use Lomkit\Rest\Text\ResolvedText;
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

    /** @phpstan-assert-if-true array{field: string, direction?: SortDirection|string|null} $sort */
    public static function requestedBy(mixed $sort): bool
    {
        return is_array($sort) && ($sort['field'] ?? null) === self::FIELD;
    }

    /** @param array{field: string, direction?: SortDirection|string|null} $sort */
    public static function fromSort(array $sort, ?ResolvedText $text): self
    {
        $direction = SortDirection::parse($sort['direction'] ?? SortDirection::Desc);

        return $text?->relevance($direction) ?? throw InvalidFieldException::notSortable(self::FIELD);
    }

    #[\Override]
    public function apply(Builder $query): void
    {
        if (! $query instanceof EloquentBuilder) {
            throw new InvalidArgumentException('A relevance order applies to the Eloquent builder of the searched resource, not to '.get_debug_type($query).'.');
        }

        $this->strategy->orderByRelevance($query, $this->text, $this->target, $this->direction);
    }
}
