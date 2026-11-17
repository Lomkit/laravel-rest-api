<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Lomkit\Rest\Extensions\Contexts\EngineProfileContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\ProvidesEngineProfiles;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\ForeignEngine;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\SingleSortProfile;
use Lomkit\Rest\Text\Engine\Profiles\EngineProfile;

final class SingleSortEngineProfiles extends Extension implements ProvidesEngineProfiles
{
    #[\Override]
    public static function name(): string
    {
        return 'single_sort';
    }

    #[\Override]
    public function engineProfile(EngineProfileContext $context): ?EngineProfile
    {
        return $context->engine instanceof ForeignEngine ? new SingleSortProfile : null;
    }
}
