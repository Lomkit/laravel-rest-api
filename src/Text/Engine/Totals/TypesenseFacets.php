<?php

namespace Lomkit\Rest\Text\Engine\Totals;

use Lomkit\Rest\Exceptions\EngineSearchException;
use Lomkit\Rest\Search\Totals\SearchTotals;
use Throwable;

/** @internal */
final readonly class TypesenseFacets
{
    private const string MISSING = 'Could not find a facet field named';

    public function __construct(private EngineTotals $totals) {}

    /** @return array<string, int|string> */
    public function options(): array
    {
        return [
            'facet_by' => implode(',', $this->totals->attributes()),
            'max_facet_values' => $this->totals->maxValues(),
            'facet_strategy' => 'exhaustive',
            'facet_sample_percent' => 100,
            'facet_sample_threshold' => 0,
        ];
    }

    public function refusal(Throwable $error): ?EngineSearchException
    {
        $message = $error->getMessage();

        if (! str_contains($message, self::MISSING)) {
            return null;
        }

        $named = preg_match('/'.self::MISSING.' `([^`]+)`/', $message, $match) === 1 ? $match[1] : null;
        $attribute = in_array($named, $this->totals->attributes(), true) ? $named : $this->totals->attributes()[0] ?? '';

        return EngineSearchException::missingFacet($this->totals->resource, $attribute, $this->totals->engine, $error instanceof EngineSearchException ? $error->getPrevious() ?? $error : $error);
    }

    /** @param array<mixed> $raw */
    public function read(array $raw, int $found): SearchTotals
    {
        if (($raw['search_cutoff'] ?? false) === true) {
            throw EngineSearchException::partialFacets($this->totals->resource, $this->totals->engine);
        }

        $facets = [];

        foreach (is_array($raw['facet_counts'] ?? null) ? $raw['facet_counts'] : [] as $facet) {
            if (! is_array($facet) || ! is_string($facet['field_name'] ?? null)) {
                continue;
            }

            if (($facet['sampled'] ?? false) === true) {
                throw EngineSearchException::partialFacets($this->totals->resource, $this->totals->engine);
            }

            $counts = [];

            foreach (is_array($facet['counts'] ?? null) ? $facet['counts'] : [] as $count) {
                if (is_array($count) && array_key_exists('value', $count) && is_int($count['count'] ?? null)) {
                    $counts[] = [$count['value'], $count['count']];
                }
            }

            $facets[$facet['field_name']] = new FacetValues($counts, is_array($facet['stats'] ?? null) ? $facet['stats'] : []);
        }

        return (new FacetTotals($this->totals))->of($facets, $found);
    }
}
