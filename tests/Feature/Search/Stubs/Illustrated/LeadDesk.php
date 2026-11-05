<?php

namespace Lomkit\Rest\Tests\Feature\Search\Stubs\Illustrated;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Lomkit\Rest\Tests\Fixtures\Newsroom\Desk;
use Lomkit\Rest\Tests\Fixtures\Newsroom\OpenNewsroomPolicy;

#[Table('desks')]
#[UsePolicy(OpenNewsroomPolicy::class)]
class LeadDesk extends Desk
{
    #[\Override]
    public function getForeignKey(): string
    {
        return 'desk_id';
    }

    #[\Override]
    public function stories(): HasMany
    {
        return $this->hasMany(IllustratedStory::class);
    }

    #[\Override]
    public function leadSummary(): HasOneThrough
    {
        return $this->hasOneThrough(StoredSummary::class, IllustratedStory::class);
    }
}
