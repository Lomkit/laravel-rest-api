<?php

namespace Lomkit\Rest\Query\Clauses;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Lomkit\Rest\Exceptions\InvalidFieldException;
use Lomkit\Rest\Fields\FieldPath;
use Lomkit\Rest\Fields\Filtering\FilterOperator;
use Lomkit\Rest\Http\Resource;

final readonly class FilterGroupClause implements Clause
{
    /** @param list<Clause> $nodes */
    public function __construct(
        private array $nodes,
        private string $boolean = 'and',
    ) {}

    /** @param array<array-key, mixed> $filters */
    public static function fromPayload(Resource $resource, array $filters, string $boolean = 'and'): self
    {
        $nodes = [];

        foreach ($filters as $filter) {
            $nodes[] = self::node($resource, is_array($filter) ? $filter : []);
        }

        return new self($nodes, $boolean);
    }

    #[\Override]
    public function apply(Builder $query): void
    {
        $query->where(function (Builder $query): void {
            foreach ($this->nodes as $node) {
                $node->apply($query);
            }
        }, null, null, $this->boolean);
    }

    /** @param array<array-key, mixed> $filter */
    private static function node(Resource $resource, array $filter): Clause
    {
        $boolean = ($filter['type'] ?? 'and') === 'or' ? 'or' : 'and';

        if (isset($filter['nested']) && is_array($filter['nested'])) {
            return self::fromPayload($resource, $filter['nested'], $boolean);
        }

        $field = is_string($filter['field'] ?? null) ? $filter['field'] : '';
        $path = FieldPath::resolve($resource, $field) ?? throw InvalidFieldException::notFilterable($field);
        $operator = self::operator($filter['operator'] ?? FilterOperator::Equal);

        if ($path->relations === []) {
            return new FieldConditionClause($path, $operator, $filter['value'] ?? null, $boolean);
        }

        return new RelationConditionClause($path, $operator, $filter['value'] ?? null, $boolean);
    }

    private static function operator(mixed $operator): FilterOperator
    {
        if ($operator instanceof FilterOperator || is_string($operator)) {
            return FilterOperator::parse($operator);
        }

        throw InvalidFieldException::unknownOperator(get_debug_type($operator));
    }
}
