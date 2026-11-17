<?php

namespace Lomkit\Rest\Text\Engine;

use LogicException;
use Lomkit\Rest\Exceptions\InvalidEngineQueryException;
use Lomkit\Rest\Fields\Filtering\FilterOperator;
use Lomkit\Rest\Search\FilterGroup;
use Lomkit\Rest\Search\RelevanceSort;
use Lomkit\Rest\Search\SortNode;
use Lomkit\Rest\Text\Engine\Groups\FilterTree;

final readonly class EngineCriteria
{
    /**
     * @param  list<EngineFilterCondition>  $filters
     * @param  list<EngineSort>  $sorts
     */
    private function __construct(
        private array $filters,
        private array $sorts,
        private ?FilterTree $tree = null,
    ) {}

    /** @param list<SortNode> $sorts */
    public static function of(FilterGroup $filters, array $sorts): self
    {
        $grouped = FilterTree::needed($filters);
        $conditions = [];

        foreach ($grouped ? [] : $filters->filters as $node) {
            $conditions[] = $node instanceof EngineFilterCondition ? $node : throw self::foreign($node);
        }

        $orders = [];

        foreach ($sorts as $sort) {
            if ($sort instanceof RelevanceSort) {
                continue;
            }

            $orders[] = $sort instanceof EngineSort ? $sort : throw self::foreign($sort);
        }

        return new self($conditions, $orders, $grouped ? FilterTree::of($filters) : null);
    }

    public function applyTo(EngineQuery $query): void
    {
        if ($this->tree !== null) {
            $query->whereGroups($this->tree);
        }

        foreach ($this->filters as $filter) {
            match ($filter->operator) {
                FilterOperator::In => $query->whereIn($filter->name, self::members($filter)),
                FilterOperator::NotIn => $query->whereNotIn($filter->name, self::members($filter)),
                default => $query->where($filter->name, $filter->operator, $filter->value),
            };
        }

        foreach ($this->sorts as $sort) {
            $query->orderBy($sort->attribute, $sort->direction());
        }
    }

    /** @return array<mixed> */
    private static function members(EngineFilterCondition $filter): array
    {
        return is_array($filter->value) ? $filter->value : throw InvalidEngineQueryException::invalidValue($filter->name, $filter->value);
    }

    private static function foreign(object $node): LogicException
    {
        return new LogicException($node::class.' does not apply to a search engine query.');
    }
}
