<?php

namespace Lomkit\Rest\Text\Engine\Totals;

use ArrayAccess;
use Lomkit\Rest\Exceptions\EngineSearchException;

/** @internal */
final readonly class AlgoliaIndexSettings
{
    /** @param list<string> $faceted */
    private function __construct(public array $faceted) {}

    public static function of(mixed $settings, EngineTotals $totals): self
    {
        if (! is_array($settings) && ! $settings instanceof ArrayAccess) {
            throw EngineSearchException::unreadFaceting($totals->resource, $totals->engine);
        }

        $distinct = $settings['distinct'] ?? null;

        if (($distinct === true || (is_int($distinct) && $distinct > 0)) && is_string($settings['attributeForDistinct'] ?? null)) {
            throw EngineSearchException::distinctFacets($totals->resource, $totals->engine);
        }

        $faceted = [];

        foreach (is_array($settings['attributesForFaceting'] ?? null) ? $settings['attributesForFaceting'] : [] as $attribute) {
            if (is_string($attribute) && ! str_starts_with($attribute, 'filterOnly(')) {
                $faceted[] = preg_replace('/^(?:searchable|afterDistinct)\((.*)\)$/', '$1', $attribute) ?? $attribute;
            }
        }

        return new self($faceted);
    }

    public function facets(string $attribute): bool
    {
        return in_array($attribute, $this->faceted, true);
    }
}
