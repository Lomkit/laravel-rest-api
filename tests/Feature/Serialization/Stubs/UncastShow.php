<?php

namespace Lomkit\Rest\Tests\Feature\Serialization\Stubs;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Lomkit\Rest\Tests\Fixtures\Festival\OpenFestivalPolicy;
use Lomkit\Rest\Tests\Fixtures\Festival\Show;

#[Table('shows')]
#[UsePolicy(OpenFestivalPolicy::class)]
class UncastShow extends Show
{
    #[\Override]
    protected function casts(): array
    {
        return array_diff_key(parent::casts(), ['featured' => true, 'starts_at' => true]);
    }
}
