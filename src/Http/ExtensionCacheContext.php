<?php

namespace Lomkit\Rest\Http;

use Lomkit\Rest\Authorization\CacheContributions;
use Lomkit\Rest\Extensions\Contexts\CacheContributionContext;
use Lomkit\Rest\Extensions\Kinds\ContributesCacheContext;

/** @internal */
final readonly class ExtensionCacheContext
{
    public static function of(Resource $resource): CacheContributions
    {
        $contributions = new CacheContributions($resource::class);
        $extensions = $resource->context()->extensions()->applicable($resource::class, ContributesCacheContext::class);

        if ($extensions === []) {
            return $contributions;
        }

        ksort($extensions);
        $user = $resource->context()->actingUser()->user;

        foreach ($extensions as $name => $extension) {
            $extension->contributeCacheContext(new CacheContributionContext($name, $resource, $user, $contributions));
        }

        return $contributions;
    }
}
