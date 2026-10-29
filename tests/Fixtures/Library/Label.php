<?php

namespace Lomkit\Rest\Tests\Fixtures\Library;

use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Unguarded]
#[UsePolicy(OpenLibraryPolicy::class)]
class Label extends Model
{
    public function labelable(): MorphTo
    {
        return $this->morphTo();
    }
}
