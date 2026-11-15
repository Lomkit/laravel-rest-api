<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Lomkit\Rest\Extensions\Contexts\WriteGuardContext;
use Lomkit\Rest\Extensions\Contexts\WriteObservationContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\GuardsWrites;
use Lomkit\Rest\Extensions\Kinds\ObservesWrites;
use Lomkit\Rest\Tests\Fixtures\Library\LoanResource;

final class LoanWritesExtension extends Extension implements GuardsWrites, ObservesWrites
{
    #[\Override]
    public function appliesTo(string $resource): bool
    {
        return $resource === LoanResource::class;
    }

    #[\Override]
    public function guardWrite(WriteGuardContext $context): void
    {
        WriteLog::$guards[] = $context;

        if (count(WriteLog::$guards) === WriteLog::$refuseAt) {
            $context->refuse(WriteLog::$refusal, WriteLog::$status);
        }
    }

    #[\Override]
    public function observeWrite(WriteObservationContext $context): void
    {
        WriteLog::$writes[] = $context;
    }
}
