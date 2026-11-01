<?php

namespace Lomkit\Rest\Text\Engine;

use Lomkit\Rest\Exceptions\InvalidEngineQueryException;
use Lomkit\Rest\Fields\Filtering\FilterOperator;
use Lomkit\Rest\Fields\SortDirection;
use Lomkit\Rest\Query\Clauses\RelevanceClause;

final readonly class EngineCriteria
{
    /**
     * @param  list<array<array-key, mixed>>  $filters
     * @param  list<array<array-key, mixed>>  $sorts
     */
    private function __construct(
        private array $filters,
        private array $sorts,
    ) {}

    /** @param array<array-key, mixed> $search */
    public static function fromSearch(array $search): self
    {
        return new self(self::entries($search['filters'] ?? null), self::entries($search['sorts'] ?? null));
    }

    public function applyTo(EngineQuery $query): void
    {
        foreach ($this->filters as $filter) {
            self::filter($query, $filter);
        }

        foreach ($this->sorts as $sort) {
            $attribute = self::name($sort['field'] ?? null);

            if ($attribute !== RelevanceClause::FIELD) {
                $query->orderBy($attribute, self::direction($sort['direction'] ?? null));
            }
        }
    }

    /** @param array<array-key, mixed> $filter */
    private static function filter(EngineQuery $query, array $filter): void
    {
        $attribute = self::name($filter['field'] ?? null);
        $declared = $filter['operator'] ?? FilterOperator::Equal->value;
        $operator = FilterOperator::parse(is_string($declared) ? $declared : '');
        $value = $filter['value'] ?? null;

        match ($operator) {
            FilterOperator::In => $query->whereIn($attribute, self::members($attribute, $value)),
            FilterOperator::NotIn => $query->whereNotIn($attribute, self::members($attribute, $value)),
            default => $query->where($attribute, $operator, $value),
        };
    }

    /** @return array<mixed> */
    private static function members(string $attribute, mixed $value): array
    {
        return is_array($value) ? $value : throw InvalidEngineQueryException::invalidValue($attribute, $value);
    }

    private static function name(mixed $name): string
    {
        return is_string($name) ? $name : '';
    }

    private static function direction(mixed $direction): SortDirection
    {
        return $direction === null ? SortDirection::Asc : SortDirection::parse(is_string($direction) ? $direction : '');
    }

    /** @return list<array<array-key, mixed>> */
    private static function entries(mixed $entries): array
    {
        return is_array($entries) ? array_values(array_filter($entries, is_array(...))) : [];
    }
}
