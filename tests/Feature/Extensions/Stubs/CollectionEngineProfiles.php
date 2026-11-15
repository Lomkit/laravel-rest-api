<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Laravel\Scout\Engines\CollectionEngine;
use Lomkit\Rest\Extensions\Contexts\EngineProfileContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\ProvidesEngineProfiles;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\ForeignProfile;
use Lomkit\Rest\Text\Engine\Profiles\EngineProfile;

final class CollectionEngineProfiles extends Extension implements ProvidesEngineProfiles
{
    #[\Override]
    public function engineProfile(EngineProfileContext $context): ?EngineProfile
    {
        return $context->engine instanceof CollectionEngine ? new ForeignProfile($context->engine) : null;
    }
}
