<?php

namespace Lomkit\Rest\Http;

use Lomkit\Rest\Exceptions\InvalidAuthorizationExtensionException;
use Lomkit\Rest\Extensions\Contexts\GatesContext;
use Lomkit\Rest\Extensions\Kinds\DeclaresGates;
use Lomkit\Rest\Search\DeclaredGateList;

/** @internal */
final readonly class ExtensionGates
{
    /** @return list<DeclaredGateList> */
    public static function of(Resource $resource): array
    {
        $extensions = $resource->context()->extensions()->applicable($resource::class, DeclaresGates::class);

        if ($extensions === []) {
            return [];
        }

        $context = new GatesContext($resource::class);
        $declared = [];

        foreach ($extensions as $name => $extension) {
            $declared[] = new DeclaredGateList(
                $extension->gates($context),
                static fn (int $index, mixed $gate): InvalidAuthorizationExtensionException => InvalidAuthorizationExtensionException::invalidGate($name, $extension::class, $resource::class, $index, $gate),
            );
        }

        return $declared;
    }
}
