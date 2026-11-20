<?php

namespace Lomkit\Rest\Text\Engine\Totals;

use Lomkit\Rest\Exceptions\EngineSearchException;
use Lomkit\Rest\Search\Totals\GroupNode;
use Lomkit\Rest\Search\Totals\SearchTotals;
use Lomkit\Rest\Search\Totals\TotalsBucket;
use Lomkit\Rest\Search\Totals\TotalsGroup;

/** @internal */
final readonly class FacetTotals
{
    public function __construct(private EngineTotals $totals) {}

    /** @param array<string, FacetValues> $facets */
    public function of(array $facets, int $found): SearchTotals
    {
        foreach ($this->totals->attributes() as $attribute) {
            if (! isset($facets[$attribute])) {
                throw EngineSearchException::missingFacet($this->totals->resource, $attribute, $this->totals->engine);
            }
        }

        $stats = [];

        foreach ($this->totals->node->measures as $measure) {
            $stats[$measure->key()] = $facets[$measure->field->name]->stats[$measure->type->value] ?? null;
        }

        return new SearchTotals(
            $this->totals->node->figures($found, $stats),
            array_map(fn (GroupNode $group): TotalsGroup => $this->group($group, $facets[$group->field->name], $found), $this->totals->node->groups),
        );
    }

    private function group(GroupNode $group, FacetValues $values, int $found): TotalsGroup
    {
        $buckets = array_map(static fn (array $count): TotalsBucket => TotalsBucket::read($group->field, $count[0], $count[1]), $values->counts);

        if (array_sum(array_column($values->counts, 1)) > $found) {
            throw EngineSearchException::multiValuedFacet($this->totals->resource, $group->field->name);
        }

        return TotalsGroup::of($group, $buckets, $found);
    }
}
