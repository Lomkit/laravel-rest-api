<?php

namespace Lomkit\Rest\Tests\Fixtures\Newsroom;

use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;

#[Unguarded]
#[UsePolicy(OpenNewsroomPolicy::class)]
class Summary extends Model
{
    public $timestamps = false;
}
