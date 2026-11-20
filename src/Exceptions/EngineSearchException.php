<?php

namespace Lomkit\Rest\Exceptions;

use RuntimeException;
use Throwable;

final class EngineSearchException extends RuntimeException
{
    /** @param array<mixed> $result */
    public static function typesenseMultiSearch(string $collection, array $result, ?Throwable $previous = null): self
    {
        $error = is_string($result['error'] ?? null) ? $result['error'] : 'an error without a message';
        $code = is_int($result['code'] ?? null) ? $result['code'] : null;
        $status = $code === null ? 'without a code' : "code {$code}";
        $fix = in_array($code, [401, 403], true) ? 'Give the API key the documents:search action on the collection.' : 'Check the filter and the collection schema.';

        return new self("Typesense refused the search of the collection [{$collection}] that the package sent through /multi_search, since its query string passes the length that a GET search accepts: {$error} ({$status}). {$fix}", $code ?? 0, $previous);
    }

    public static function unexpectedMultiSearch(string $collection): self
    {
        return new self("Typesense gave an unexpected answer to the search of the collection [{$collection}] that the package sent through /multi_search: the answer holds no search result. Check the version of the Typesense server and of typesense/typesense-php.");
    }

    public static function partialFacets(string $resource, string $engine): self
    {
        return new self("The search engine [{$engine}] answered the search of the resource [{$resource}] with totals that do not count every matched row (a search cutoff or sampled facets), so the package refuses to show them. Remove the search time limit or the sampling of the index, or ask without totals.");
    }

    public static function multiValuedFacet(string $resource, string $attribute): self
    {
        return new self("The search engine counted more values of the attribute [{$attribute}] than rows matched for the resource [{$resource}]: the index holds several values per row for it. A grouped attribute holds one value per row: index a single value, or remove it from ScoutEngine::groupable().");
    }

    public static function missingFacet(string $resource, string $attribute, string $engine, ?Throwable $previous = null): self
    {
        return new self("The search engine [{$engine}] returned no facet for the attribute [{$attribute}] that the resource [{$resource}] groups or measures. Make the attribute facetable in the index settings (facet: true on Typesense, a filterable attribute on Meilisearch), then index the rows again.", 0, $previous);
    }

    public static function cappedFacetValues(string $resource, string $attribute, int $max): self
    {
        return new self("The search engine returns at most {$max} values of the attribute [{$attribute}] for the resource [{$resource}], and the index does not sort them by count, so the package cannot tell the most frequent values. Raise the faceting maxValuesPerFacet of the index above the values of the attribute, or sort its facet values by count with a limit below {$max}.");
    }

    public static function unreadFaceting(string $resource, string $engine, Throwable $previous): self
    {
        return new self("The package could not read the faceting settings of the index of the resource [{$resource}] on [{$engine}], which tell whether its totals are complete: {$previous->getMessage()} Give the API key the settings.get action, or ask without totals.", 0, $previous);
    }
}
