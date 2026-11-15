<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Extensions\Contexts\WriteGuardContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\GuardsWrites;
use Lomkit\Rest\Tests\Fixtures\Library\ShelfResource;

final class HeldShelfGuard extends Extension implements GuardsWrites
{
    #[\Override]
    public function appliesTo(string $resource): bool
    {
        return $resource === ShelfResource::class;
    }

    #[\Override]
    public function guardWrite(WriteGuardContext $context): void
    {
        $keys = array_map(fn (Model $row): mixed => $row->getKey(), $context->rows ?? []);

        if (in_array(101, $keys, true)) {
            $context->refuse('The shelf is on hold.', 423);
        }
    }
}
