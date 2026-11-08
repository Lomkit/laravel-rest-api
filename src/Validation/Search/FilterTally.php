<?php

namespace Lomkit\Rest\Validation\Search;

use Lomkit\Rest\Fields\Filtering\FilterOperator;
use Lomkit\Rest\Validation\FilterCounts;

final readonly class FilterTally
{
    private const array PATTERNS = [FilterOperator::Like, FilterOperator::NotLike, FilterOperator::ILike, FilterOperator::NotILike];

    public function __construct(
        private int $depth,
    ) {}

    public function of(mixed $entries, int $level = 0): FilterCounts
    {
        $counts = new FilterCounts;

        if (! is_array($entries) || ! array_is_list($entries)) {
            return $counts;
        }

        foreach ($entries as $entry) {
            if (is_array($entry)) {
                $counts = $counts->plus($this->entry($entry, $level));
            }
        }

        return $counts;
    }

    /** @param array<array-key, mixed> $entry */
    private function entry(array $entry, int $level): FilterCounts
    {
        $group = array_key_exists('relation', $entry);
        $own = new FilterCounts(
            relations: $group || self::throughRelation($entry['field'] ?? null) ? 1 : 0,
            patterns: ! $group && self::pattern($entry['operator'] ?? null) ? 1 : 0,
        );

        if ($level >= $this->depth) {
            return $own;
        }

        $counts = $own->plus($this->of($entry['nested'] ?? null, $level + 1));
        $types = $group ? ($entry['types'] ?? null) : null;

        foreach (is_array($types) ? $types : [] as $list) {
            $counts = $counts->plus($this->of($list, $level + 1));
        }

        return $counts;
    }

    private static function throughRelation(mixed $field): bool
    {
        if (! is_string($field)) {
            return false;
        }

        $segments = explode('.', $field);
        array_pop($segments);

        if ($segments !== [] && $segments[array_key_last($segments)] === 'pivot') {
            array_pop($segments);
        }

        return $segments !== [];
    }

    private static function pattern(mixed $operator): bool
    {
        return is_string($operator) && in_array(FilterOperator::tryFrom($operator), self::PATTERNS, true);
    }
}
