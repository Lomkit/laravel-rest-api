<?php

namespace Lomkit\Rest\Tests\Fixtures\Library;

use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Unguarded]
#[UsePolicy(OpenLibraryPolicy::class)]
class Loan extends Model
{
    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }
}
