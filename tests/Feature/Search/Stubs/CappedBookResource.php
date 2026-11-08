<?php

namespace Lomkit\Rest\Tests\Feature\Search\Stubs;

use Lomkit\Rest\Scopes\Scope;
use Lomkit\Rest\Tests\Fixtures\Library\BookResource;

class CappedBookResource extends BookResource
{
    public static string $model = CappedBook::class;

    public int $defaultLimit = 10;

    public ?int $maxTotal = 20;

    #[\Override]
    public function limits(): array
    {
        return [5, 10];
    }

    #[\Override]
    public function relations(): array
    {
        return [];
    }

    #[\Override]
    public function scopes(): array
    {
        return [Scope::make('borrowedTwice')];
    }
}
