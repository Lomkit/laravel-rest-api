<?php

namespace Lomkit\Rest\Search\Totals;

use JsonSerializable;

/** @internal */
final readonly class TotalsGroup implements JsonSerializable
{
    /** @param list<TotalsBucket> $buckets */
    public function __construct(
        public string $field,
        public array $buckets,
        public TotalsFigures $other,
        public bool $truncated,
    ) {}

    /** @param list<TotalsBucket> $values */
    public static function of(GroupNode $group, array $values, int $rows): self
    {
        $values = array_values(array_filter($values, static fn (TotalsBucket $bucket): bool => $bucket->value !== null));
        usort($values, static fn (TotalsBucket $first, TotalsBucket $second): int => [$second->count, $first->value] <=> [$first->count, $second->value]);
        $buckets = array_slice($values, 0, $group->limit);
        $counted = array_sum(array_map(static fn (TotalsBucket $bucket): int => $bucket->count, $buckets));

        return new self($group->field->name, $buckets, new TotalsFigures(max(0, $rows - $counted)), count($values) > $group->limit);
    }

    /** @return array{field: string, buckets: list<array{value: int|string|bool|null, count: int}>, other: array<string, int|float|null>, truncated: bool} */
    #[\Override]
    public function jsonSerialize(): array
    {
        return [
            'field' => $this->field,
            'buckets' => array_map(static fn (TotalsBucket $bucket): array => $bucket->jsonSerialize(), $this->buckets),
            'other' => $this->other->jsonSerialize(),
            'truncated' => $this->truncated,
        ];
    }
}
