<?php

namespace Lomkit\Rest\Extensions;

use Lomkit\Rest\Exceptions\InvalidExtensionException;
use Throwable;

/** @internal */
final readonly class ExtensionName
{
    public const int MAX_LENGTH = 32;

    private const string PATTERN = '/^[a-z][a-z0-9]*(?:_[a-z0-9]+)*\z/';

    /** @param class-string<Extension> $class */
    public static function of(string $class): string
    {
        try {
            $name = $class::name();
        } catch (Throwable $exception) {
            throw InvalidExtensionException::unreadableName($class, $exception);
        }

        if (! self::matches($name)) {
            throw InvalidExtensionException::invalidName($class, $name);
        }

        return $name;
    }

    public static function matches(string $name): bool
    {
        return strlen($name) <= self::MAX_LENGTH && preg_match(self::PATTERN, $name) === 1;
    }
}
