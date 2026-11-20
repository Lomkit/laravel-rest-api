<?php

namespace Lomkit\Rest\Text\Engine\Totals;

use Lomkit\Rest\Exceptions\EngineSearchException;
use Lomkit\Rest\Search\Totals\SearchTotals;

/** @internal */
final readonly class AlgoliaFacets
{
    public const int MAX_VALUES = 1000;

    public function __construct(private EngineTotals $totals) {}

    /** @return array<string, mixed> */
    public function options(): array
    {
        return [
            'facets' => $this->totals->attributes(),
            'maxValuesPerFacet' => min(self::MAX_VALUES, $this->totals->maxValues()),
            'sortFacetValuesBy' => 'count',
            'facetingAfterDistinct' => true,
        ];
    }

    /** @param array<mixed> $raw */
    public function read(array $raw, int $found): SearchTotals
    {
        $exhaustive = is_array($raw['exhaustive'] ?? null) ? $raw['exhaustive'] : [];

        foreach ([$raw['exhaustiveFacetsCount'] ?? null, $raw['exhaustiveNbHits'] ?? null, $exhaustive['facetsCount'] ?? null, $exhaustive['facetValues'] ?? null, $exhaustive['nbHits'] ?? null] as $flag) {
            if ($flag === false) {
                throw EngineSearchException::partialFacets($this->totals->resource, $this->totals->engine);
            }
        }

        $distribution = is_array($raw['facets'] ?? null) ? $raw['facets'] : [];
        $stats = is_array($raw['facets_stats'] ?? null) ? $raw['facets_stats'] : [];
        $facets = [];

        foreach ($this->totals->attributes() as $attribute) {
            if (! is_array($distribution[$attribute] ?? null) && ! is_array($stats[$attribute] ?? null)) {
                continue;
            }

            $counts = [];

            foreach (is_array($distribution[$attribute] ?? null) ? $distribution[$attribute] : [] as $value => $count) {
                if (is_int($count)) {
                    $counts[] = [$value, $count];
                }
            }

            $facets[$attribute] = new FacetValues($counts, is_array($stats[$attribute] ?? null) ? $stats[$attribute] : []);
        }

        return (new FacetTotals($this->totals))->of($facets, $found);
    }
}
