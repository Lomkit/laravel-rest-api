<?php

namespace Lomkit\Rest\Tests\Feature\Search\Totals\Stubs;

use Lomkit\Rest\Scopes\Scope;
use Lomkit\Rest\Tests\Fixtures\Festival\ShowResource;

class ScopedShowResource extends ShowResource
{
    public static string $model = TotalledShow::class;

    #[\Override]
    public function scopes(): array
    {
        return [Scope::make('commentedTwice'), Scope::make('commented'), Scope::make('distinctlyCommented'), Scope::make('withHeadliners')];
    }
}
