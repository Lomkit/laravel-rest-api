<?php

namespace Lomkit\Rest\Tests\Feature\Serialization\Stubs;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Lomkit\Rest\Tests\Fixtures\Festival\Artist;
use Lomkit\Rest\Tests\Fixtures\Festival\OpenFestivalPolicy;
use Lomkit\Rest\Tests\Fixtures\Festival\Show;

#[Table('shows')]
#[UsePolicy(OpenFestivalPolicy::class)]
class UncastShow extends Show
{
    #[\Override]
    public function artists(): BelongsToMany
    {
        return $this->belongsToMany(Artist::class, 'artist_show', 'show_id')->withPivot('role', 'headliner', 'confirmed_at');
    }

    #[\Override]
    protected function casts(): array
    {
        return array_diff_key(parent::casts(), ['featured' => true, 'starts_at' => true]);
    }
}
