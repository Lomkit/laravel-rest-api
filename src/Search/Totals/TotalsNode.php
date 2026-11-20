<?php

namespace Lomkit\Rest\Search\Totals;

/** @internal */
final readonly class TotalsNode
{
    /**
     * @param  list<MeasureNode>  $measures
     * @param  list<GroupNode>  $groups
     */
    public function __construct(
        public array $measures,
        public array $groups,
    ) {}

    /** @return list<string> */
    public function measuredNames(): array
    {
        return array_values(array_unique(array_map(static fn (MeasureNode $measure): string => $measure->field->name, $this->measures)));
    }

    /** @param array<string, mixed> $raw */
    public function figures(int $count, array $raw): TotalsFigures
    {
        $measures = [];

        foreach ($this->measures as $measure) {
            $measures[$measure->key()] = $measure->value($count === 0 ? null : $raw[$measure->key()] ?? null);
        }

        return new TotalsFigures($count, $measures);
    }

    public function empty(): SearchTotals
    {
        return new SearchTotals(
            $this->figures(0, []),
            array_map(static fn (GroupNode $group): TotalsGroup => TotalsGroup::of($group, [], 0), $this->groups),
        );
    }
}
