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
        public int $maxPage,
    ) {}

    /** @param list<int> $allowed */
    public static function of(Resource $resource, array $allowed): self
    {
        foreach (['defaultLimit' => $resource->defaultLimit, 'includeLimit' => $resource->includeLimit] as $property => $limit) {
            if (! in_array($limit, $allowed, true)) {
                throw InvalidResourceException::limitNotAllowed($resource::class, $property, $limit, $allowed);
            }
        }

        $largest = max([1, ...$allowed]);

        if ($resource->maxTotal !== null && $resource->maxTotal < $largest) {
            throw InvalidResourceException::maxTotalTooLow($resource::class, $resource->maxTotal, $largest);
        }

        return new self($allowed, $resource->defaultLimit, $resource->includeLimit, $resource->maxTotal, self::maxPage($allowed));
    }

    /** @param list<int> $allowed */
    public function narrowedTo(array $allowed, int $defaultLimit, int $includeLimit, ?int $maxTotal): self
    {
        return new self($allowed, $defaultLimit, $includeLimit, $maxTotal, self::maxPage($allowed));
    }

    /** @param list<int> $allowed */
    private static function maxPage(array $allowed): int
    {
        $largest = max([1, ...$allowed]);

        return $largest === 1 ? PHP_INT_MAX : intdiv(PHP_INT_MAX, $largest) + 1;
    }
}
