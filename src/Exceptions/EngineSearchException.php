<?php

namespace Lomkit\Rest\Exceptions;

use RuntimeException;

final class EngineSearchException extends RuntimeException
{
    /** @param array<mixed>|null $result */
    public static function typesenseMultiSearch(string $collection, ?array $result): self
    {
        $error = is_string($result['error'] ?? null) ? $result['error'] : 'no search result';
        $code = is_int($result['code'] ?? null) ? $result['code'] : 500;

        return new self("Typesense refused the search of the collection [{$collection}] that the package sent through /multi_search, since its query string passes the length that a GET search accepts: {$error} (code {$code}). Check the filter and the collection schema, and give the API key the documents:search action on the collection.", $code);
    }
}
