<?php

namespace Lomkit\Rest\Exceptions;

use Lomkit\Rest\Http\Routing\Endpoint;

final class InvalidWriteExtensionException extends InvalidExtensionException
{
    public static function refusalStatus(string $name, string $class, string $resource, Endpoint $endpoint, int $status): self
    {
        return self::about($name, "The [{$name}] extension {$class} refuses a {$endpoint->value} write on {$resource} with the status {$status}. Pass a client error status from 400 to 499 to refuse(), such as 403 or 429: a refusal answers the client and rolls the write back.");
    }
}
