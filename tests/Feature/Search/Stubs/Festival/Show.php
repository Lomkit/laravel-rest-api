<?php

namespace Lomkit\Rest\Tests\Feature\Search\Stubs\Festival;

use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Lomkit\Rest\Tests\Fixtures\Publishing\OpenPublishingPolicy;

#[Unguarded]
#[UsePolicy(OpenPublishingPolicy::class)]
class Show extends Model
{
    public $timestamps = false;

    public function notes(): MorphMany
    {
        return $this->morphMany(Note::class, 'notable');
    }

    public function note(): MorphOne
    {
        return $this->morphOne(Note::class, 'notable');
    }

    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }

    #[\Override]
    protected function casts(): array
    {
        return ['featured' => 'boolean'];
    }
}
