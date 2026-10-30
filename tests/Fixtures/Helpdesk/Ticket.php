<?php

namespace Lomkit\Rest\Tests\Fixtures\Helpdesk;

use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Laravel\Scout\Searchable;

#[Unguarded]
#[UsePolicy(TicketPolicy::class)]
class Ticket extends Model
{
    use Searchable;

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class)->withPivot('position');
    }

    public function labels(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'labelable')->withPivot('position');
    }

    /** @return array<string, mixed> */
    public function toSearchableArray(): array
    {
        return ['title' => $this->title];
    }
}
