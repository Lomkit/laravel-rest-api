<?php

namespace Lomkit\Rest\Tests\Fixtures\Publishing;

use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Unguarded]
#[UseFactory(DraftFactory::class)]
#[UsePolicy(OpenPublishingPolicy::class)]
class Draft extends Model
{
    use HasFactory;
    use HasUuids;

    public function author(): BelongsTo
    {
        return $this->belongsTo(Author::class);
    }
}
