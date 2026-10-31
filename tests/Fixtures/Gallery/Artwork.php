<?php

namespace Lomkit\Rest\Tests\Fixtures\Gallery;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Unguarded]
#[UsePolicy(OpenGalleryPolicy::class)]
class Artwork extends Model
{
    public function artist(): BelongsTo
    {
        return $this->belongsTo(Artist::class);
    }

    public function exhibitions(): BelongsToMany
    {
        return $this->belongsToMany(Exhibition::class)->withPivot('position', 'wall');
    }

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    #[Scope]
    protected function pricedUnder(Builder $query, int|float $max): void
    {
        $query->where('price', '<=', $max);
    }

    /** @return array<string, string> */
    #[\Override]
    protected function casts(): array
    {
        return [
            'medium' => Medium::class,
            'dimensions' => 'array',
            'price' => 'float',
        ];
    }
}
