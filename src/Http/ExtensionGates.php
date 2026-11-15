<?php

namespace Lomkit\Rest\Http;

use Lomkit\Rest\Extensions\Contexts\GatesContext;
use Lomkit\Rest\Extensions\Kinds\DeclaresGates;

/** @internal */
final readonly class ExtensionGates
{
    /** @return array<string, array<mixed>> */
    public static function of(Resource $resource): array
    {
        $extensions = $resource->context()->extensions()->applicable($resource::class, DeclaresGates::class);

        if ($extensions === []) {
            return [];
        }

        ksort($extensions);
        $context = new GatesContext($resource::class);
        $declared = [];

        foreach ($extensions as $name => $extension) {
            $declared['The ['.$name.'] extension '.$extension::class.' in gates() for '.$resource::class] = $extension->gates($context);
        }

        return $declared;
    }
}
