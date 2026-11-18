<?php

namespace Lomkit\Rest\Exceptions;

use RuntimeException;
use Throwable;

final class EngineSearchException extends RuntimeException
{
    private const array TYPESENSE_ERRORS = [
        400 => 'Typesense\Exceptions\RequestMalformed',
        401 => 'Typesense\Exceptions\RequestUnauthorized',
        404 => 'Typesense\Exceptions\ObjectNotFound',
        409 => 'Typesense\Exceptions\ObjectAlreadyExists',
        422 => 'Typesense\Exceptions\ObjectUnprocessable',
        503 => 'Typesense\Exceptions\ServiceUnavailable',
    ];

    private const string TYPESENSE_SERVER_ERROR = 'Typesense\Exceptions\ServerError';

    /** @param array<mixed> $result */
    public static function typesenseMultiSearch(string $collection, array $result): self
    {
        $error = is_string($result['error'] ?? null) ? $result['error'] : 'an error without a message';
        $code = is_int($result['code'] ?? null) ? $result['code'] : null;
        $status = $code === null ? 'without a code' : "code {$code}";
        $fix = in_array($code, [401, 403], true) ? 'Give the API key the documents:search action on the collection.' : 'Check the filter and the collection schema.';

        return new self("Typesense refused the search of the collection [{$collection}] that the package sent through /multi_search, since its query string passes the length that a GET search accepts: {$error} ({$status}). {$fix}", $code ?? 0, self::typesenseError($code, $error));
    }

    public static function unexpectedMultiSearch(string $collection): self
    {
        return new self("Typesense gave an unexpected answer to the search of the collection [{$collection}] that the package sent through /multi_search: the answer holds no search result. Check the version of the Typesense server and of typesense/typesense-php.");
    }

    private static function typesenseErrorClass(?int $code): string
    {
        return self::TYPESENSE_ERRORS[$code ?? 0] ?? self::TYPESENSE_SERVER_ERROR;
    }

    private static function typesenseError(?int $code, string $error): ?Throwable
    {
        $class = self::typesenseErrorClass($code);
        $exception = class_exists($class) ? new $class : null;

        if ($exception instanceof Throwable && method_exists($exception, 'setMessage')) {
            $exception->setMessage($error);
        }

        return $exception instanceof Throwable ? $exception : null;
    }
}
