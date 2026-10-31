<?php

namespace Lomkit\Rest\Tests\Fixtures\Newsroom;

use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;

#[Unguarded]
#[UsePolicy(OpenNewsroomPolicy::class)]
class Edition extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $primaryKey = 'code';

    protected $keyType = 'string';
}
