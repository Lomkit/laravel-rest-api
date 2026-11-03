<?php

namespace Lomkit\Rest\Http;

final readonly class ResourceLimits
{
    /** @param list<int> $allowed */
    private function __construct(
        public array $allowed,
        public int $defaultLimit,
    ) {}

    /** @param list<int> $allowed */
    public static function of(Resource $resource, array $allowed): self
    {
        return new self($allowed, $resource->defaultLimit);
    }
}
