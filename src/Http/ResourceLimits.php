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
        public ?int $maxTotal,
    ) {}

    /** @param list<int> $allowed */
    public static function of(Resource $resource, array $allowed): self
    {
        foreach (['defaultLimit' => $resource->defaultLimit, 'includeLimit' => $resource->includeLimit] as $property => $limit) {
            if (! in_array($limit, $allowed, true)) {
                throw InvalidResourceException::limitNotAllowed($resource::class, $property, $limit, $allowed);
            }
        }

        $floor = max([1, ...$allowed]);

        if ($resource->maxTotal !== null && $resource->maxTotal < $floor) {
            throw InvalidResourceException::maxTotalTooLow($resource::class, $resource->maxTotal, $floor);
        }

        return new self($allowed, $resource->defaultLimit, $resource->includeLimit, $resource->maxTotal);
    }
}
