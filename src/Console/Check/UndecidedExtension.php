<?php

namespace Lomkit\Rest\Console\Check;

use Lomkit\Rest\Extensions\ExtensionRegistry;
use Lomkit\Rest\Extensions\Kinds\ExtensionKind;
use Lomkit\Rest\Http\Resource;
use Throwable;

/** @internal */
final readonly class UndecidedExtension
{
    /** @param class-string<Resource> $resource */
    public static function problem(ExtensionRegistry $registry, string $name, string $resource): ?string
    {
        try {
            $registry->appliesTo($name, $resource);
        } catch (Throwable $exception) {
            return "The [{$name}] extension {$registry->classOf($name)} throws while it tells whether it applies to {$resource}: {$exception->getMessage()}";
        }

        return null;
    }

    /**
     * @param  class-string<ExtensionKind>  $kind
     * @param  class-string<Resource>  $resource
     */
    public static function first(ExtensionRegistry $registry, string $kind, string $resource): ?string
    {
        foreach (array_keys($registry->ofKind($kind)) as $name) {
            $problem = self::problem($registry, $name, $resource);

            if ($problem !== null) {
                return $problem;
            }
        }

        return null;
    }
}
