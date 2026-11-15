<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Laravel\Scout\Engines\DatabaseEngine;
use Lomkit\Rest\Extensions\Contexts\EngineProfileContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\ProvidesEngineProfiles;
use Lomkit\Rest\Text\Engine\Profiles\DatabaseProfile;
use Lomkit\Rest\Text\Engine\Profiles\EngineProfile;

final class NoSqlDatabaseProfiles extends Extension implements ProvidesEngineProfiles
{
    #[\Override]
    public static function name(): string
    {
        return 'nosql';
    }

    #[\Override]
    public function engineProfile(EngineProfileContext $context): ?EngineProfile
    {
        return $context->engine instanceof DatabaseEngine ? new NoSqlDatabaseProfile(new DatabaseProfile($context->engine)) : null;
    }
}
