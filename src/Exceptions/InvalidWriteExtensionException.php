<?php

namespace Lomkit\Rest\Exceptions;

use Lomkit\Rest\Http\Routing\Endpoint;

final class InvalidWriteExtensionException extends InvalidExtensionException
{
    public static function refusalStatus(string $name, string $class, string $resource, Endpoint $endpoint, int $status): self
    {
        return self::about($name, "The [{$name}] extension {$class} refuses a {$endpoint->value} write on {$resource} with the status {$status}. Pass 403, another client error status from 400 to 499 such as 423 or 429, or 503 to refuse(): a refusal answers the client and rolls the write back.");
    }

    public static function reservedRefusalStatus(string $name, string $class, string $resource, Endpoint $endpoint, int $status): self
    {
        return self::about($name, "The [{$name}] extension {$class} refuses a {$endpoint->value} write on {$resource} with the status {$status}, which the package answers with another body (409 conflicts, 422 validation errors, 428 confirmations). Refuse with 423 or 429.");
    }
}
