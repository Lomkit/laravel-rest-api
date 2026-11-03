<?php

namespace Lomkit\Rest\Tests\Feature\Search\Stubs;

use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Lomkit\Rest\Tests\Fixtures\Library\Label;
use Lomkit\Rest\Tests\Fixtures\Library\OpenLibraryPolicy;

#[UsePolicy(OpenLibraryPolicy::class)]
class CastLabel extends Label
{
    protected $table = 'labels';

    protected $casts = ['labelable_type' => LabelableType::class];
}
