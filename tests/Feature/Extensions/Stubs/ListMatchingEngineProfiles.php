<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Lomkit\Rest\Extensions\Contexts\EngineProfileContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\ProvidesEngineProfiles;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\ForeignEngine;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\ListMatchingProfile;
use Lomkit\Rest\Text\Engine\Profiles\EngineProfile;

final class ListMatchingEngineProfiles extends Extension implements ProvidesEngineProfiles
{
    #[\Override]
    public static function name(): string
    {
        return 'list_matching';
    }

    #[\Override]
    public function engineProfile(EngineProfileContext $context): ?EngineProfile
    {
        return $context->engine instanceof ForeignEngine ? new ListMatchingProfile($context->engine) : null;
    }
}
