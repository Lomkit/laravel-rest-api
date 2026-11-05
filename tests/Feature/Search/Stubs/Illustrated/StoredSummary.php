<?php

namespace Lomkit\Rest\Tests\Feature\Search\Stubs\Illustrated;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Lomkit\Rest\Tests\Fixtures\Newsroom\OpenNewsroomPolicy;
use Lomkit\Rest\Tests\Fixtures\Newsroom\Summary;

#[Table('summaries')]
#[UsePolicy(OpenNewsroomPolicy::class)]
class StoredSummary extends Summary
{
    /** @return BelongsTo<IllustratedStory, $this> */
    public function story(): BelongsTo
    {
        return $this->belongsTo(IllustratedStory::class);
    }
}
