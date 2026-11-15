<?php

namespace Lomkit\Rest\Exceptions;

final class InvalidDocumentationExtensionException extends ExtensionException
{
    public static function statusOutOfRange(string $name, string $class, string $route, int $status): self
    {
        return self::about($name, "The [{$name}] extension {$class} documents the status {$status} on the operation of [{$route}]. Document an error status from 400 to 599: the package documents the success response.");
    }

    public static function invalidResponseName(string $name, string $class, string $response): self
    {
        return self::about($name, "The [{$name}] extension {$class} names a response [{$response}]. Give letters and digits that start with a letter, such as QuotaReached: the response component is then named [{$name}.QuotaReached].");
    }

    public static function statusTaken(string $name, string $class, string $route, int $status, string $owner): self
    {
        return self::about($name, "The [{$name}] extension {$class} documents the status {$status} on the operation of [{$route}], which {$owner} already documents. An operation has one response per status: document another status, or leave this operation out of one of them.");
    }

    public static function metaWithoutKind(string $name, string $class, string $resource, string $kind): self
    {
        return self::about($name, "The [{$name}] extension {$class} documents meta for {$resource} that it never adds: it does not implement {$kind}. Implement {$kind}, or leave this schema out of documentMeta().");
    }
}
