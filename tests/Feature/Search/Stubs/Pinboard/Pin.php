<?php

namespace Lomkit\Rest\Tests\Feature\Search\Stubs\Pinboard;

use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Lomkit\Rest\Tests\Fixtures\Publishing\OpenPublishingPolicy;

#[Unguarded]
#[UsePolicy(OpenPublishingPolicy::class)]
class Pin extends Model
{
    public function pinnable(): MorphTo
    {
        return $this->morphTo();
    }
}
