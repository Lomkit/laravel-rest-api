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

    /** @param array<mixed> $faceting */
    public static function of(array $faceting): self
    {
        $max = $faceting['maxValuesPerFacet'] ?? null;
        $sorts = [];

        foreach (is_array($faceting['sortFacetValuesBy'] ?? null) ? $faceting['sortFacetValuesBy'] : [] as $attribute => $sort) {
            if (is_string($sort)) {
                $sorts[(string) $attribute] = $sort;
            }
        }

        return new self(is_int($max) && $max > 0 ? $max : 100, $sorts);
    }

    public function sortedByCount(string $attribute): bool
    {
        return ($this->sorts[$attribute] ?? $this->sorts['*'] ?? 'alpha') === 'count';
    }
}
