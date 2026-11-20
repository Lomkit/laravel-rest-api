<?php

namespace Lomkit\Rest\Text\Engine\Totals;

/** @internal */
final readonly class FacetingSettings
{
    /** @param array<string, string> $sorts */
    public function __construct(
        public int $maxValues,
        private array $sorts,
    ) {}

    /** @param array<mixed>|null $faceting */
    public static function of(?array $faceting): ?self
    {
        if ($faceting === null || ! is_int($faceting['maxValuesPerFacet'] ?? null) || $faceting['maxValuesPerFacet'] < 1) {
            return null;
        }

        $sorts = [];

        foreach (is_array($faceting['sortFacetValuesBy'] ?? null) ? $faceting['sortFacetValuesBy'] : [] as $attribute => $sort) {
            if (is_string($sort)) {
                $sorts[(string) $attribute] = $sort;
            }
        }

        return new self($faceting['maxValuesPerFacet'], $sorts);
    }

    public function sortedByCount(string $attribute): bool
    {
        return ($this->sorts[$attribute] ?? $this->sorts['*'] ?? 'alpha') === 'count';
    }
}
