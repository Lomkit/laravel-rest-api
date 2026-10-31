<?php

namespace Lomkit\Rest\Tests\Fixtures\Newsroom;

use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

#[Unguarded]
#[UsePolicy(OpenNewsroomPolicy::class)]
class Keyword extends Model
{
    public $timestamps = false;

    public function stories(): MorphToMany
    {
        return $this->morphedByMany(Story::class, 'keywordable');
    }

    public function desks(): MorphToMany
    {
        return $this->morphedByMany(Desk::class, 'keywordable');
    }
}
