<?php

namespace Lomkit\Rest\Tests\Fixtures\Helpdesk;

use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

#[Unguarded]
#[UsePolicy(TeamPolicy::class)]
class Team extends Model
{
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function replies(): HasManyThrough
    {
        return $this->hasManyThrough(Reply::class, Ticket::class);
    }
}
