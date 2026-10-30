<?php

namespace Lomkit\Rest\Tests\Fixtures\Newsroom;

use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Unguarded]
#[UsePolicy(OpenNewsroomPolicy::class)]
class Topic extends Model
{
    public $timestamps = false;

    public function stories(): BelongsToMany
    {
        return $this->belongsToMany(Story::class);
    }
}
