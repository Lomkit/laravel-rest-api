<?php

namespace Lomkit\Rest\Tests\Feature\MorphTargets\Stubs;

use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Lomkit\Rest\Tests\Fixtures\Library\Label;
use Lomkit\Rest\Tests\Fixtures\Library\OpenLibraryPolicy;

#[UsePolicy(OpenLibraryPolicy::class)]
class DeclaredLabel extends Label
{
    protected $table = 'labels';

    public function book(): MorphTo
    {
        return $this->morphTo('book', 'labelable_type', 'labelable_id');
    }
}
