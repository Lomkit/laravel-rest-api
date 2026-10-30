<?php

namespace Lomkit\Rest\Tests\Feature\Deletion\Stubs;

use Illuminate\Database\Eloquent\Attributes\Connection;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Tests\Fixtures\Archive\OpenArchivePolicy;

#[Connection('secondary')]
#[Table('documents')]
#[Unguarded]
#[UsePolicy(OpenArchivePolicy::class)]
class SecondaryDocument extends Model {}
