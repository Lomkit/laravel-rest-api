<?php

namespace Lomkit\Rest\Tests\Fixtures\Festival;

use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Unguarded]
#[UsePolicy(OpenFestivalPolicy::class)]
class Stage extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $primaryKey = 'code';

    protected $keyType = 'string';

    public function shows(): HasMany
    {
        return $this->hasMany(Show::class, 'stage_code');
    }

    public function comments(): HasManyThrough
    {
        return $this->hasManyThrough(Comment::class, Show::class, 'stage_code');
    }

    public function notes(): MorphMany
    {
        return $this->morphMany(Note::class, 'notable');
    }
}
