<?php

namespace Lomkit\Rest\Tests\Fixtures\Publishing;

use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Unguarded]
#[UseFactory(TagFactory::class)]
#[UsePolicy(OpenPublishingPolicy::class)]
class Tag extends Model
{
    use HasFactory;
}
