<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Extensions\Contexts\WriteGuardContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\GuardsWrites;

final class FrozenRowGuard extends Extension implements GuardsWrites
{
    #[\Override]
    public static function name(): string
    {
        return 'frozen';
    }

    #[\Override]
    public function guardWrite(WriteGuardContext $context): void
    {
        $keys = array_map(fn (Model $row): mixed => $row->getKey(), $context->rows);

        if (array_intersect($keys, WriteLog::$frozen) !== []) {
            $context->refuse('The document is frozen.', 423);
        }
    }
}
