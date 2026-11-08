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

    /** @param list<array{key: int|string|bool|null, partials: list<int|float|null>|null, previous?: list<int|float|null>|null}> $groups */
    public static function of(Aggregate $function, MetricColumn $group, array $groups, ?int $limit, bool $compared = false): self
    {
        $parts = array_map(fn (array $row): array => [self::part($function, $row['key'], $group->label($row['key']), $row['partials'], $row['previous'] ?? null, $compared), $row], $groups);
        usort($parts, fn (array $one, array $two): int => self::order($one[0], $two[0]));
        $kept = array_column(array_slice($parts, 0, $limit), 0);
        $folded = $limit === null ? [] : array_column(array_slice($parts, $limit), 1);

        if ($folded === []) {
            return new self($kept);
        }

        $partials = self::fold($function, array_map(fn (array $row): ?array => $row['partials'], $folded));
        $previous = self::fold($function, array_map(fn (array $row): ?array => $row['previous'] ?? null, $folded));

        return new self($kept, self::part($function, null, self::OTHER, $partials, $previous, $compared));
    }

    /** @param array<int|string, string> $labels */
    public function labels(array $labels, ?string $other = null): self
    {
        $renamed = new KeyLabels($labels);
        $parts = array_map(fn (PartitionPart $part): PartitionPart => $part->labelled($renamed->for($part->key, $part->label)), $this->parts);

        return new self($parts, $other === null ? $this->other : $this->other?->labelled($other));
    }

    /** @return array{parts: list<PartitionPart>, other: array{label: string|null, value: int|float|null, previous?: int|float|null}|null} */
    #[\Override]
    public function jsonSerialize(): array
    {
        if ($this->other === null) {
            return ['parts' => $this->parts, 'other' => null];
        }

        $other = ['label' => $this->other->label, 'value' => $this->other->value];

        return ['parts' => $this->parts, 'other' => $this->other->compared ? [...$other, 'previous' => $this->other->previous] : $other];
    }

    /**
     * @param  list<int|float|null>|null  $partials
     * @param  list<int|float|null>|null  $previous
     */
    private static function part(Aggregate $function, int|string|bool|null $key, ?string $label, ?array $partials, ?array $previous, bool $compared): PartitionPart
    {
        return new PartitionPart($key, $label, $partials === null ? null : $function->value($partials), $compared, $previous === null ? null : $function->value($previous));
    }

    /**
     * @param  list<list<int|float|null>|null>  $partials
     * @return list<int|float|null>|null
     */
    private static function fold(Aggregate $function, array $partials): ?array
    {
        $present = array_values(array_filter($partials, fn (?array $one): bool => $one !== null));
        $first = array_shift($present);

        return $first === null ? null : array_reduce($present, $function->merge(...), $first);
    }

    private static function order(PartitionPart $one, PartitionPart $two): int
    {
        return [$one->value === null, $two->value, $one->key === null, $one->key] <=> [$two->value === null, $one->value, $two->key === null, $two->key];
    }
}
