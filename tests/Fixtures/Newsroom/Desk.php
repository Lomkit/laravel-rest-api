<?php

namespace Lomkit\Rest\Tests\Fixtures\Newsroom;

use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

#[Unguarded]
#[UsePolicy(OpenNewsroomPolicy::class)]
class Desk extends Model
{
    public $timestamps = false;

    public function stories(): HasMany
    {
        return $this->hasMany(Story::class);
    }

    public function comments(): HasManyThrough
    {
        return $this->hasManyThrough(Comment::class, Story::class);
    }

    public function leadSummary(): HasOneThrough
    {
        return $this->hasOneThrough(Summary::class, Story::class);
    }

    public function keywords(): MorphToMany
    {
        return $this->morphToMany(Keyword::class, 'keywordable');
    }

    public function reporters(): BelongsToMany
    {
        return $this->belongsToMany(Reporter::class)
            ->withPivot('role', 'pass_number', 'created_at')
            ->withTimestamps('assigned_at', 'reviewed_at');
    }
}
