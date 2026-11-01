<?php

namespace Lomkit\Rest\Tests\Fixtures\Gallery;

use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;

#[Unguarded]
#[UsePolicy(OpenGalleryPolicy::class)]
class Artist extends Model {}
