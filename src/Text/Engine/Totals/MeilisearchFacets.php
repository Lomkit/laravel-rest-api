<?php

namespace Lomkit\Rest\Text\Engine\Totals;

use Lomkit\Rest\Exceptions\EngineSearchException;
use Lomkit\Rest\Search\Totals\GroupNode;
use Lomkit\Rest\Search\Totals\SearchTotals;
use Throwable;

/** @internal */
final readonly class MeilisearchFacets
{
    public function __construct(
        private EngineTotals $totals,
        private FacetingSettings $settings,
    ) {}

    /** @return array{facets: list<string>} */
    public function options(): array
    {
        return ['facets' => $this->totals->attributes()];
    }

    public function refusal(Throwable $error): ?EngineSearchException
    {
        if ((get_object_vars($error)['errorCode'] ?? null) !== 'invalid_search_facets') {
            return null;
        }

        $named = array_values(array_filter($this->totals->attributes(), static fn (string $attribute): bool => str_contains($error->getMessage(), "`{$attribute}`")));

        return EngineSearchException::missingFacet($this->totals->resource, $named[0] ?? $this->totals->attributes()[0] ?? '', $this->totals->engine, $error);
    }

    /** @param array<mixed> $raw */
    public function read(array $raw, int $found): SearchTotals
    {
        $distribution = is_array($raw['facetDistribution'] ?? null) ? $raw['facetDistribution'] : [];
        $stats = is_array($raw['facetStats'] ?? null) ? $raw['facetStats'] : [];
        $facets = [];

        foreach ($this->totals->attributes() as $attribute) {
            if (! is_array($distribution[$attribute] ?? null)) {
                continue;
            }

            $counts = [];

            foreach ($distribution[$attribute] as $value => $count) {
                if (is_int($count)) {
                    $counts[] = [$value, $count];
                }
            }

            $facets[$attribute] = new FacetValues($counts, is_array($stats[$attribute] ?? null) ? $stats[$attribute] : []);
        }

        foreach ($this->totals->node->groups as $group) {
            $this->ensureComplete($group, count($facets[$group->field->name]->counts ?? []));
        }

        return (new FacetTotals($this->totals))->of($facets, $found);
    }

    private function ensureComplete(GroupNode $group, int $returned): void
    {
        $max = $this->settings->maxValues;

        if ($returned < $max || ($this->settings->sortedByCount($group->field->name) && $group->limit + 1 <= $max)) {
            return;
        }

        throw EngineSearchException::cappedFacetValues($this->totals->resource, $group->field->name, $max);
    }
}
