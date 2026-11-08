<?php

namespace Lomkit\Rest\Tests\Feature\Search\Stubs\Festival;

use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Lomkit\Rest\Tests\Fixtures\Publishing\OpenPublishingPolicy;

#[Unguarded]
#[UsePolicy(OpenPublishingPolicy::class)]
class Note extends Model
{
    public $timestamps = false;

    public function notable(): MorphTo
    {
        return $this->morphTo();
    }
}
