<?php

namespace Lomkit\Rest\Tests\Feature\Search\Stubs\Festival;

use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Lomkit\Rest\Tests\Fixtures\Publishing\OpenPublishingPolicy;

#[Unguarded]
#[UsePolicy(OpenPublishingPolicy::class)]
class Tag extends Model
{
    public $timestamps = false;

    public function shows(): MorphToMany
    {
        return $this->morphedByMany(Show::class, 'taggable');
    }

    public function stages(): MorphToMany
    {
        return $this->morphedByMany(Stage::class, 'taggable');
    }
}
