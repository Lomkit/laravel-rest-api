<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Lomkit\Rest\Extensions\Contexts\EngineProfileContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\ProvidesEngineProfiles;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\ForeignProfile;
use Lomkit\Rest\Tests\Fixtures\Ledger\VoucherResource;
use Lomkit\Rest\Text\Engine\Profiles\EngineProfile;

final class UnappliedEngineProfiles extends Extension implements ProvidesEngineProfiles
{
    #[\Override]
    public static function name(): string
    {
        return 'unapplied';
    }

    #[\Override]
    public function appliesTo(string $resource): bool
    {
        return $resource !== VoucherResource::class;
    }

    #[\Override]
    public function engineProfile(EngineProfileContext $context): ?EngineProfile
    {
        EngineLog::$calls[] = 'UnappliedEngineProfiles';

        return new ForeignProfile($context->engine);
    }
}
