<?php

namespace Lomkit\Rest\Metrics;

final readonly class PartitionResult implements MetricResult
{
    public const string OTHER = 'Other';

    /** @param list<PartitionPart> $parts */
    public function __construct(
        public array $parts,
        public ?PartitionPart $other = null,
    ) {}

    /** @param list<array{key: int|string|bool|null, partials: list<int|float|null>}> $groups */
    public static function of(Aggregate $function, MetricColumn $group, array $groups, ?int $limit): self
    {
        $parts = array_map(fn (array $row): array => [new PartitionPart($row['key'], $group->label($row['key']), $function->value($row['partials'])), $row['partials']], $groups);
        usort($parts, fn (array $one, array $two): int => self::order($one[0], $two[0]));
        $kept = array_column(array_slice($parts, 0, $limit), 0);
        $folded = $limit === null ? [] : array_column(array_slice($parts, $limit), 1);
        $first = array_shift($folded);

        if ($first === null) {
            return new self($kept);
        }

        return new self($kept, new PartitionPart(null, self::OTHER, $function->value(array_reduce($folded, $function->merge(...), $first))));
    }

    /** @param array<int|string, string> $labels */
    public function labels(array $labels, ?string $other = null): self
    {
        $parts = array_map(fn (PartitionPart $part): PartitionPart => $part->labelled(self::label($labels, $part)), $this->parts);

        return new self($parts, $other === null ? $this->other : $this->other?->labelled($other));
    }

    /** @return array{parts: list<PartitionPart>, other: array{label: string|null, value: int|float|null}|null} */
    #[\Override]
    public function jsonSerialize(): array
    {
        $other = $this->other === null ? null : ['label' => $this->other->label, 'value' => $this->other->value];

        return ['parts' => $this->parts, 'other' => $other];
    }

    private static function order(PartitionPart $one, PartitionPart $two): int
    {
        return [$one->value === null, $two->value, $one->key === null, $one->key] <=> [$two->value === null, $one->value, $two->key === null, $two->key];
    }

    /** @param array<int|string, string> $labels */
    private static function label(array $labels, PartitionPart $part): ?string
    {
        $key = is_bool($part->key) ? (int) $part->key : $part->key;

        return $key !== null && array_key_exists($key, $labels) ? $labels[$key] : $part->label;
    }
}
