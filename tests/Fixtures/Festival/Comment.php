<?php

namespace Lomkit\Rest\Tests\Fixtures\Festival;

use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Unguarded]
#[UsePolicy(OpenFestivalPolicy::class)]
class Comment extends Model
{
    public $timestamps = false;

    public function show(): BelongsTo
    {
        return $this->belongsTo(Show::class);
    }
}
