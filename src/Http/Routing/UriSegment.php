<?php

namespace Lomkit\Rest\Http\Routing;

use Lomkit\Rest\Exceptions\InvalidResourceRouteException;

final readonly class UriSegment
{
    private const PATTERN = '/^[\p{L}\p{N}_-]+$/u';

    private function __construct(public string $value) {}

    public static function resourceName(string $name): self
    {
        if (str_contains($name, '.')) {
            throw InvalidResourceRouteException::nestedName($name);
        }

        if (str_contains($name, '/')) {
            throw InvalidResourceRouteException::prefixedName($name);
        }

        if (preg_match(self::PATTERN, $name) !== 1) {
            throw InvalidResourceRouteException::malformedName($name);
        }

        return new self($name);
    }

    public static function verb(string $key, mixed $verb): self
    {
        if (! is_string($verb) || preg_match(self::PATTERN, $verb) !== 1) {
            throw InvalidResourceRouteException::invalidVerb($key);
        }

        return new self($verb);
    }
}
