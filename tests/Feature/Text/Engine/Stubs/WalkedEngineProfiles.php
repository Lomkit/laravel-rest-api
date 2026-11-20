<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine\Stubs;

use Laravel\Scout\Engines\CollectionEngine;
use Laravel\Scout\Engines\DatabaseEngine;
use Lomkit\Rest\Extensions\Contexts\EngineProfileContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\ProvidesEngineProfiles;
use Lomkit\Rest\Text\Engine\Profiles\EngineProfile;
use Lomkit\Rest\Text\Engine\Profiles\EngineProfiles;
use Lomkit\Rest\Text\Engine\Profiles\ListsWithoutText;

final class WalkedEngineProfiles extends Extension implements ProvidesEngineProfiles
{
    public static int $pageSize = 2;

    public static ?int $window = null;

    public static ?int $reachable = PHP_INT_MAX;

    /** @var list<array{int, int}> */
    public static array $pages = [];

    public static function reset(): void
    {
        self::$pageSize = 2;
        self::$window = null;
        self::$reachable = PHP_INT_MAX;
        self::$pages = [];
    }

    #[\Override]
    public static function name(): string
    {
        return 'walked';
    }

    #[\Override]
    public function engineProfile(EngineProfileContext $context): ?EngineProfile
    {
        if (! $context->engine instanceof CollectionEngine && ! $context->engine instanceof DatabaseEngine) {
            return null;
        }

        $profile = EngineProfiles::for($context->engine, $context->resource);

        return $profile instanceof ListsWithoutText ? new WalkedProfile($profile) : null;
    }
}
