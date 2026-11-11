<?php

namespace Lomkit\Rest\Tests\Feature\Relations\Persisters\Stubs\Memberships;

use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Tests\Fixtures\Archive\OpenArchivePolicy;

#[Unguarded]
#[UsePolicy(OpenArchivePolicy::class)]
class Member extends Model {}
