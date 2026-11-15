<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Lomkit\Rest\Actions\ActionTransaction;
use Lomkit\Rest\Extensions\Contexts\WriteGuardContext;
use Lomkit\Rest\Extensions\Contexts\WriteObservationContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\GuardsWrites;
use Lomkit\Rest\Extensions\Kinds\ObservesWrites;

final class ModeChangingExtension extends Extension implements GuardsWrites, ObservesWrites
{
    #[\Override]
    public static function name(): string
    {
        return 'mode';
    }

    #[\Override]
    public function guardWrite(WriteGuardContext $context): void
    {
        if ($context->action !== null) {
            $context->action->transaction = ActionTransaction::Whole;
            $context->action->chunkCount = 10;
        }
    }

    #[\Override]
    public function observeWrite(WriteObservationContext $context): void
    {
        if ($context->action !== null) {
            $context->action->transaction = ActionTransaction::None;
            $context->action->chunkCount = 10;
        }
    }
}
