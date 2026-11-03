<?php

namespace Lomkit\Rest\Http;

use Lomkit\Rest\Exceptions\InvalidResourceException;

final readonly class ResourceLimits
{
    /** @param list<int> $allowed */
    private function __construct(
        public array $allowed,
        public int $defaultLimit,
        public int $includeLimit,
    ) {}

    /** @param list<int> $allowed */
    public static function of(Resource $resource, array $allowed): self
    {
        foreach (['defaultLimit' => $resource->defaultLimit, 'includeLimit' => $resource->includeLimit] as $property => $limit) {
            if (! in_array($limit, $allowed, true)) {
                throw InvalidResourceException::limitNotAllowed($resource::class, $property, $limit, $allowed);
            }
        }

        return new self($allowed, $resource->defaultLimit, $resource->includeLimit);
    }
}
