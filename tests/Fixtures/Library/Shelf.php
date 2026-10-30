<?php

namespace Lomkit\Rest\Tests\Fixtures\Library;

use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;

#[Unguarded]
#[UsePolicy(OpenLibraryPolicy::class)]
class Shelf extends Model {}
