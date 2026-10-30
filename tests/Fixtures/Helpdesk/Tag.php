<?php

namespace Lomkit\Rest\Tests\Fixtures\Helpdesk;

use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Unguarded]
#[UsePolicy(TagPolicy::class)]
class Tag extends Model
{
    use SoftDeletes;

    public function labelledTickets(): MorphToMany
    {
        return $this->morphedByMany(Ticket::class, 'labelable')->withPivot('position', 'weight', 'added_by', 'note', 'created_at');
    }
}
