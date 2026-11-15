<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Lomkit\Rest\Extensions\Contexts\GatesContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\DeclaresGates;
use Lomkit\Rest\Http\Resource;

class FirstGates extends Extension implements DeclaresGates
{
    public static ?string $named = null;

    #[\Override]
    public static function name(): string
    {
        return self::$named ?? 'acl';
    }

    /** @var list<class-string<Resource>>|null */
    public static ?array $resources = null;

    /** @var list<mixed> */
    public static array $gates = [];

    /** @var list<GatesContext> */
    public static array $contexts = [];

    public static function reset(): void
    {
        self::$named = null;
        self::$resources = null;
        self::$gates = [];
        self::$contexts = [];
    }

    #[\Override]
    public function appliesTo(string $resource): bool
    {
        return self::$resources === null || in_array($resource, self::$resources, true);
    }

    #[\Override]
    public function gates(GatesContext $context): array
    {
        self::$contexts[] = $context;

        /** @var list<string> */
        return self::$gates;
    }
}
