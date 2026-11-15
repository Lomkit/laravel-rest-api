<?php

namespace Lomkit\Rest\Console\Check;

use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\ExtensionRegistry;
use Lomkit\Rest\Extensions\Kinds\ExtensionKind;
use Lomkit\Rest\Http\Resource;
use Throwable;

/** @internal */
final readonly class UndecidedExtension
{
    /** @param class-string<Resource> $resource */
    public static function problem(ExtensionRegistry $registry, string $name, Extension $extension, string $resource): ?string
    {
        try {
            $registry->appliesTo($name, $resource);
        } catch (Throwable $exception) {
            return "The [{$name}] extension ".$extension::class." throws while it tells whether it applies to {$resource}: {$exception->getMessage()}";
        }

        return null;
    }

    /**
     * @param  class-string<ExtensionKind>  $kind
     * @param  class-string<Resource>  $resource
     */
    public static function first(ExtensionRegistry $registry, string $kind, string $resource): ?string
    {
        foreach ($registry->ofKind($kind) as $name => $extension) {
            $problem = self::problem($registry, $name, $extension, $resource);

            if ($problem !== null) {
                return $problem;
            }
        }

        return null;
    }
}
