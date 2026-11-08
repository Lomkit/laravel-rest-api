<?php

namespace Lomkit\Rest\Validation\Search;

final readonly class RelationFilterCount
{
    public function __construct(
        private int $depth,
    ) {}

    public function of(mixed $entries, int $level = 0): int
    {
        if (! is_array($entries) || ! array_is_list($entries)) {
            return 0;
        }

        $count = 0;

        foreach ($entries as $entry) {
            if (is_array($entry)) {
                $count += $this->entry($entry, $level);
            }
        }

        return $count;
    }

    /** @param array<array-key, mixed> $entry */
    private function entry(array $entry, int $level): int
    {
        $group = array_key_exists('relation', $entry);
        $own = $group || self::throughRelation($entry['field'] ?? null) ? 1 : 0;

        if ($level >= $this->depth) {
            return $own;
        }

        $below = $this->of($entry['nested'] ?? null, $level + 1);
        $types = $group ? ($entry['types'] ?? null) : null;

        foreach (is_array($types) ? $types : [] as $list) {
            $below += $this->of($list, $level + 1);
        }

        return $own + $below;
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
}
