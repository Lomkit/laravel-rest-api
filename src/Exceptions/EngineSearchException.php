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
}
