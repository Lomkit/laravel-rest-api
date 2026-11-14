<?php

namespace Lomkit\Rest\Http;

use Lomkit\Rest\Exceptions\InvalidQueryExtensionException;
use Lomkit\Rest\Extensions\Contexts\LimitsContext;
use Lomkit\Rest\Extensions\Kinds\NarrowsLimits;

/** @internal */
final readonly class LimitNarrowing
{
    public static function of(ResourceLimits $limits, Resource $resource): ResourceLimits
    {
        $extensions = $resource->context()->extensions()->applicable($resource::class, NarrowsLimits::class);

        if ($extensions === []) {
            return $limits;
        }

        ksort($extensions);
        $requests = new LimitRequests($resource::class);
        $user = $resource->context()->actingUser()->user;

        foreach ($extensions as $name => $extension) {
            $extension->narrowLimits(new LimitsContext($name, $resource, $user, $requests));
        }

        return self::narrowed($limits, $requests, $resource::class, array_keys($extensions));
    }

    public static function appliesTo(Resource $resource): bool
    {
        return $resource->context()->extensions()->applicable($resource::class, NarrowsLimits::class) !== [];
    }

    /** @param list<string> $names */
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
