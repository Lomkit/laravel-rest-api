<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Lomkit\Rest\Extensions\Contexts\IndexAttributesContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\DeclaresIndexAttributes;

abstract class DeclaredIndexExtension extends Extension implements DeclaresIndexAttributes
{
    /** @var array<class-string, list<mixed>> */
    public static array $declared = [];

    #[\Override]
    public function indexAttributes(IndexAttributesContext $context): array
    {
        EngineLog::$calls[] = class_basename(static::class);
        EngineLog::$contexts[] = $context;

        return self::$declared[static::class] ?? [];
    }
}
