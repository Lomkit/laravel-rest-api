<?php

namespace Lomkit\Rest\Text\Engine\Totals;

use Lomkit\Rest\Search\Totals\GroupNode;
use Lomkit\Rest\Search\Totals\SearchTotals;
use Lomkit\Rest\Search\Totals\TotalsNode;

/** @internal */
final readonly class EngineTotals
{
    /** @param array<string, int> $scales */
    public function __construct(
        public TotalsNode $node,
        public string $resource,
        public string $engine,
        public array $scales = [],
    ) {}

    public function rounded(string $attribute, mixed $value): mixed
    {
        $scale = $this->scales[$attribute] ?? null;

        return $scale === null || ! is_numeric($value) ? $value : round((float) $value, $scale);
    }

    /** @return list<string> */
    public function attributes(): array
    {
        $grouped = array_map(static fn (GroupNode $group): string => $group->field->name, $this->node->groups);

        return array_values(array_unique([...$grouped, ...$this->node->measuredNames()]));
    }

    public function maxValues(): int
    {
        return max([0, ...array_map(static fn (GroupNode $group): int => $group->limit, $this->node->groups)]) + 1;
    }

    public function empty(): SearchTotals
    {
        return $this->node->empty();
    }
}
