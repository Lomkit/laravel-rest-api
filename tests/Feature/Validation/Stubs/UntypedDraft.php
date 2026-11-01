<?php

namespace Lomkit\Rest\Tests\Feature\Validation\Stubs;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Tests\Fixtures\Publishing\OpenPublishingPolicy;

#[Table('drafts', incrementing: false)]
#[Unguarded]
#[UsePolicy(OpenPublishingPolicy::class)]
class UntypedDraft extends Model {}
