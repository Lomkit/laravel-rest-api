<?php

namespace Lomkit\Rest\Tests\Fixtures\Atlas;

use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;

#[UsePolicy(AtlasPolicy::class)]
class Country extends Model {}
