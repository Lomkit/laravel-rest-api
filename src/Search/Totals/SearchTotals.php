<?php

namespace Lomkit\Rest\Search\Totals;

use JsonSerializable;

/** @internal */
final readonly class SearchTotals implements JsonSerializable
{
    /** @param list<TotalsGroup> $groups */
    public function __construct(
        public TotalsFigures $all,
        public array $groups,
        public bool $exact = true,
    ) {}

    /** @return array{exact: bool, all: array<string, int|float|null>, groups: list<array{field: string, buckets: list<array{value: int|string|bool|null, count: int}>, other: array<string, int|float|null>, truncated: bool}>} */
    #[\Override]
    public function jsonSerialize(): array
    {
        return [
            'exact' => $this->exact,
            'all' => $this->all->jsonSerialize(),
            'groups' => array_map(static fn (TotalsGroup $group): array => $group->jsonSerialize(), $this->groups),
        ];
    }
}
