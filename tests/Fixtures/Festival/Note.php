<?php

namespace Lomkit\Rest\Tests\Fixtures\Festival;

use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Unguarded]
#[UsePolicy(OpenFestivalPolicy::class)]
class Note extends Model
{
    public $timestamps = false;

    public function notable(): MorphTo
    {
        return $this->morphTo();
    }
}
