<?php

namespace Lomkit\Rest\Extensions;

use Lomkit\Rest\Exceptions\InvalidExtensionException;

final readonly class ExtensionName
{
    public const int MAX_LENGTH = 32;

    private const string PATTERN = '/^[a-z][a-z0-9_]*$/';

    public static function ensureValid(string $name): void
    {
        if (strlen($name) > self::MAX_LENGTH || preg_match(self::PATTERN, $name) !== 1) {
            throw InvalidExtensionException::invalidName($name);
        }
    }
}
