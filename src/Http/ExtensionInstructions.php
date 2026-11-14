<?php

namespace Lomkit\Rest\Http;

use Lomkit\Rest\Extensions\Contexts\InstructionsContext;
use Lomkit\Rest\Extensions\Kinds\DeclaresInstructions;
use Lomkit\Rest\Instructions\Instruction;

/** @internal */
final readonly class ExtensionInstructions
{
    /** @return array<string, list<Instruction|class-string<Instruction>>> */
    public static function of(Resource $resource): array
    {
        $extensions = $resource->context()->extensions()->applicable($resource::class, DeclaresInstructions::class);

        if ($extensions === []) {
            return [];
        }

        ksort($extensions);
        $context = new InstructionsContext($resource::class);
        $declared = [];

        foreach ($extensions as $name => $extension) {
            $declared[$name] = $extension->instructions($context);
        }

        return $declared;
    }
}
