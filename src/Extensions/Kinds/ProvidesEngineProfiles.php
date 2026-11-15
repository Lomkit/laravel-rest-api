<?php

namespace Lomkit\Rest\Extensions\Kinds;

use Lomkit\Rest\Extensions\Contexts\EngineProfileContext;
use Lomkit\Rest\Text\Engine\Profiles\EngineProfile;

interface ProvidesEngineProfiles extends ExtensionKind
{
    public function engineProfile(EngineProfileContext $context): ?EngineProfile;
}
