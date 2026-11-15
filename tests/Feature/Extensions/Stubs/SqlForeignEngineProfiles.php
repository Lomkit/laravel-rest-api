<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Lomkit\Rest\Extensions\Contexts\EngineProfileContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\ProvidesEngineProfiles;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\ForeignEngine;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\ForeignProfile;
use Lomkit\Rest\Text\Engine\Profiles\EngineProfile;

final class SqlForeignEngineProfiles extends Extension implements ProvidesEngineProfiles
{
    #[\Override]
    public function engineProfile(EngineProfileContext $context): ?EngineProfile
    {
        return $context->engine instanceof ForeignEngine ? new ForeignProfile($context->engine, appliesRows: true) : null;
    }
}
