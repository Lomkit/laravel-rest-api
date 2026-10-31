<?php

namespace Lomkit\Rest\Tests\Fixtures\Newsroom;

use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Unguarded]
#[UsePolicy(OpenNewsroomPolicy::class)]
class Photo extends Model
{
    public $timestamps = false;

    public function imageable(): MorphTo
    {
        return $this->morphTo();
    }
}
