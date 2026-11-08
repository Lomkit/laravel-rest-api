<?php

namespace Lomkit\Rest\Metrics;

final readonly class ComparedGroups
{
    public function __construct(
        private RankedGroups $current,
        private ?RankedGroups $previous,
    ) {}

    /** @return list<PartitionPart> */
    public function parts(MetricColumn $group): array
    {
        $current = $this->current->groups;
        $previous = $this->previous->groups ?? [];
        $parts = array_map(fn (array $row): PartitionPart => new PartitionPart($row['key'], $group->label($row['key']), $row['value'], true, self::find($previous, $row['key'])), $current);
        $keys = array_map(fn (PartitionPart $part): int|string|bool|null => $part->key, $parts);

        foreach ($previous as $row) {
            if (! in_array($row['key'], $keys, true)) {
                $parts[] = new PartitionPart($row['key'], $group->label($row['key']), null, true, $row['value']);
            }
        }

        return $parts;
    }

    /** @param list<int|string|bool|null> $kept */
    public function other(array $kept): PartitionPart
    {
        return new PartitionPart(null, PartitionResult::OTHER, $this->current->others($kept), true, $this->previous?->others($kept));
    }

    /** @param list<array{key: int|string|bool|null, value: int|float|null}> $groups */
    private static function find(array $groups, int|string|bool|null $key): int|float|null
    {
        foreach ($groups as $group) {
            if ($group['key'] === $key) {
                return $group['value'];
            }
        }

        return null;
    }
}
