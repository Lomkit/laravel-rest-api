<?php

namespace Lomkit\Rest\Tests\Fixtures\Helpdesk;

use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Unguarded]
#[UsePolicy(TagPolicy::class)]
class Tag extends Model
{
    use SoftDeletes;
}
