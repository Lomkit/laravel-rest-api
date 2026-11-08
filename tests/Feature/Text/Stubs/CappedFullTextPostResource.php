<?php

namespace Lomkit\Rest\Tests\Feature\Text\Stubs;

class CappedFullTextPostResource extends FullTextPostResource
{
    public int $defaultLimit = 10;

    public ?int $maxTotal = 20;

    #[\Override]
    public function limits(): array
    {
        return [5, 10];
    }
}
