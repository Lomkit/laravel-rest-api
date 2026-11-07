<?php

namespace Lomkit\Rest\Tests\Fixtures\Festival;

use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;

#[Unguarded]
#[UsePolicy(OpenFestivalPolicy::class)]
class Label extends Model
{
    public $timestamps = false;
}
