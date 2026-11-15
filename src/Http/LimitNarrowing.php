<?php

namespace Lomkit\Rest\Http;

use Lomkit\Rest\Exceptions\InvalidQueryExtensionException;
use Lomkit\Rest\Extensions\Contexts\LimitsContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\NarrowsLimits;
use WeakMap;

/** @internal */
final class LimitNarrowing
{
    /** @var WeakMap<Resource, array{string, string}>|null */
    private static ?WeakMap $narrowing = null;

    public static function of(ResourceLimits $limits, Resource $resource): ResourceLimits
    {
        $narrowing = self::$narrowing ??= new WeakMap;

        if (isset($narrowing[$resource])) {
            [$name, $class] = $narrowing[$resource];

            throw InvalidQueryExtensionException::limitsReadWhileNarrowing($name, $class, $resource::class);
        }

        $extensions = $resource->context()->extensions()->applicable($resource::class, NarrowsLimits::class);

        if ($extensions === []) {
            return $limits;
        }

        $requests = new LimitRequests($resource::class);
        $user = $resource->context()->actingUser()->user;

        try {
            foreach ($extensions as $name => $extension) {
                $narrowing[$resource] = [$name, $extension::class];
                $extension->narrowLimits(new LimitsContext($name, $extension::class, $resource, $user, $limits, $requests));
            }
        } finally {
            unset($narrowing[$resource]);
        }

        return self::narrowed($limits, $requests, $resource::class, array_map(static fn (string $name, Extension $extension): array => [$name, $extension::class], array_keys($extensions), array_values($extensions)));
    }

    public static function appliesTo(Resource $resource): bool
    {
        return $resource->context()->extensions()->applicable($resource::class, NarrowsLimits::class) !== [];
    }

    /** @param non-empty-list<array{string, string}> $names */
    private static function narrowed(ResourceLimits $limits, LimitRequests $requests, string $resource, array $names): ResourceLimits
    {
        $maxTotal = self::smallest($limits->maxTotal, $requests->totalCeiling());
        $ceiling = self::smallest($requests->sizeCeiling(), $maxTotal);
        $allowed = array_values(array_filter($limits->allowed, static fn (int $size): bool => $requests->allows($size, $ceiling)));

        if ($allowed === []) {
            throw InvalidQueryExtensionException::noPageSize($resource, $names);
        }

        $default = self::largestUpTo($allowed, $limits->defaultLimit)
            ?? throw InvalidQueryExtensionException::defaultAboveSizes($resource, $names, $limits->defaultLimit, $allowed);
        $includeCeiling = min($limits->includeLimit, $requests->includeCeiling() ?? $limits->includeLimit);
        $include = self::largestUpTo($allowed, $includeCeiling)
            ?? throw InvalidQueryExtensionException::includeLimitAboveSizes($resource, $names, $includeCeiling, $allowed);

        return $limits->narrowedTo($allowed, $default, $include, $maxTotal);
    }

    /** @param list<int> $sizes */
    private static function largestUpTo(array $sizes, int $ceiling): ?int
    {
        $fitting = array_filter($sizes, static fn (int $size): bool => $size <= $ceiling);

        return $fitting === [] ? null : max($fitting);
    }

    private static function smallest(?int $first, ?int $second): ?int
    {
        if ($first === null || $second === null) {
            return $first ?? $second;
        }

        return min($first, $second);
    }
}
