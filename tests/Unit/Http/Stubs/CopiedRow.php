<?php

namespace Lomkit\Rest\Tests\Unit\Http\Stubs;

use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Casts\AsCollection;
use Illuminate\Database\Eloquent\Model;

#[Unguarded]
final class CopiedRow extends Model
{
    /** @return array<string, string> */
    #[\Override]
    protected function casts(): array
    {
        return ['tags' => AsCollection::class];
    }
}
