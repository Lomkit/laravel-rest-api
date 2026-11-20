<?php

namespace Lomkit\Rest\Text\Engine\Totals;

use Lomkit\Rest\Exceptions\EngineSearchException;
use Lomkit\Rest\Search\Totals\SearchTotals;

/** @internal */
final readonly class AlgoliaFacets
{
    public const int MAX_VALUES = 1000;

    public function __construct(
        private EngineTotals $totals,
        private AlgoliaIndexSettings $settings,
    ) {}

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
        $measured = $this->totals->node->measuredNames();
        $facets = [];

        foreach ($this->totals->attributes() as $attribute) {
            if (! $this->settings->facets($attribute)) {
                throw EngineSearchException::missingFacet($this->totals->resource, $attribute, $this->totals->engine);
            }

            $counts = [];

            foreach (is_array($distribution[$attribute] ?? null) ? $distribution[$attribute] : [] as $value => $count) {
                if (is_int($count)) {
                    $counts[] = [$value, $count];
                }
            }

            $numbers = is_array($stats[$attribute] ?? null) ? $stats[$attribute] : [];

            if ($found > 0 && $counts !== [] && in_array($attribute, $measured, true) && ! is_numeric($numbers['sum'] ?? null)) {
                throw EngineSearchException::unmeasurableFacet($this->totals->resource, $attribute, 'string');
            }

            $facets[$attribute] = new FacetValues($counts, $numbers);
        }

        return (new FacetTotals($this->totals))->of($facets, $found);
    }
}
