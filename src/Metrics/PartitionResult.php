<?php

namespace Lomkit\Rest\Metrics;

use Closure;

final readonly class PartitionResult implements MetricResult
{
    public const string OTHER = 'Other';

    /** @param list<PartitionPart> $parts */
    public function __construct(
        public array $parts,
        public ?PartitionPart $other = null,
    ) {}

    public static function of(RankedGroups $values, MetricColumn $group, int $limit): self
    {
        return self::limited(self::ranked($values, $group), $limit, fn (array $kept): PartitionPart => new PartitionPart(null, self::OTHER, $values->others($kept)));
    }

    public static function compared(ComparedGroups $values, MetricColumn $group, int $limit): self
    {
        $parts = $values->parts($group);
        usort($parts, self::order(...));

        return self::limited($parts, $limit, $values->other(...));
    }

    /** @return list<PartitionPart> */
    public static function ranked(RankedGroups $values, MetricColumn $group): array
    {
        $parts = array_map(fn (array $row): PartitionPart => new PartitionPart($row['key'], $group->label($row['key']), $row['value']), $values->groups);
        usort($parts, self::order(...));

        return $parts;
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
     * @param  list<PartitionPart>  $parts
     * @param  Closure(list<int|string|bool|null>): PartitionPart  $other
     */
    private static function limited(array $parts, int $limit, Closure $other): self
    {
        $kept = array_slice($parts, 0, $limit);

        if (count($kept) === count($parts)) {
            return new self($kept);
        }

        return new self($kept, $other(array_map(fn (PartitionPart $part): int|string|bool|null => $part->key, $kept)));
    }

    private static function order(PartitionPart $one, PartitionPart $two): int
    {
        return [$one->value === null, $two->value, $one->key === null, $one->key] <=> [$two->value === null, $one->value, $two->key === null, $two->key];
    }
}
