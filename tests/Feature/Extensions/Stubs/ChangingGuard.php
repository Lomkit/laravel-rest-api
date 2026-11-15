<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Lomkit\Rest\Extensions\Contexts\WriteGuardContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\GuardsWrites;

final class ChangingGuard extends Extension implements GuardsWrites
{
    #[\Override]
    public static function name(): string
    {
        return 'quota';
    }

    #[\Override]
    public function guardWrite(WriteGuardContext $context): void
    {
        WriteLog::$guards[] = $context;

        foreach ($context->rows as $row) {
            $row->setAttribute('title', 'Changed');
        }
    }
}
