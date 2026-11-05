<?php

namespace Lomkit\Rest\Tests\Feature\Search\Stubs\Illustrated;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Lomkit\Rest\Tests\Fixtures\Newsroom\OpenNewsroomPolicy;
use Lomkit\Rest\Tests\Fixtures\Newsroom\Story;

#[Table('stories')]
#[UsePolicy(OpenNewsroomPolicy::class)]
class IllustratedStory extends Story
{
    #[\Override]
    public function getMorphClass(): string
    {
        return Story::class;
    }

    #[\Override]
    public function getForeignKey(): string
    {
        return 'story_id';
    }

    #[\Override]
    public function cover(): MorphOne
    {
        return $this->morphOne(KeywordedPhoto::class, 'imageable');
    }

    #[\Override]
    public function photos(): MorphMany
    {
        return $this->morphMany(KeywordedPhoto::class, 'imageable');
    }

    /** @return MorphOne<KeywordedPhoto, $this> */
    public function latestPhoto(): MorphOne
    {
        return $this->morphOne(KeywordedPhoto::class, 'imageable')->latestOfMany();
    }
}
