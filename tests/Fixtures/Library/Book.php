<?php

namespace Lomkit\Rest\Tests\Fixtures\Library;

use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

#[Unguarded]
#[UsePolicy(OpenLibraryPolicy::class)]
class Book extends Model
{
    public function loan(): HasOne
    {
        return $this->hasOne(Loan::class);
    }

    public function latestLoan(): HasOne
    {
        return $this->hasOne(Loan::class)->latestOfMany();
    }

    public function label(): MorphOne
    {
        return $this->morphOne(Label::class, 'labelable');
    }

    public function latestLabel(): MorphOne
    {
        return $this->morphOne(Label::class, 'labelable')->latestOfMany();
    }

    public function labels(): MorphMany
    {
        return $this->morphMany(Label::class, 'labelable');
    }

    public function shelves(): BelongsToMany
    {
        return $this->belongsToMany(Shelf::class);
    }
}
