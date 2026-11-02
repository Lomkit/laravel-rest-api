<?php

namespace Lomkit\Rest\Tests\Feature\Serialization\Stubs\Charts;

use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;

#[Unguarded]
#[UsePolicy(OpenChartsPolicy::class)]
class Rating extends Model {}
