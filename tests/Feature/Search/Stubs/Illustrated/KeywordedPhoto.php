<?php

namespace Lomkit\Rest\Tests\Feature\Search\Stubs\Illustrated;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Lomkit\Rest\Tests\Fixtures\Newsroom\Keyword;
use Lomkit\Rest\Tests\Fixtures\Newsroom\OpenNewsroomPolicy;
use Lomkit\Rest\Tests\Fixtures\Newsroom\Photo;

#[Table('photos')]
#[UsePolicy(OpenNewsroomPolicy::class)]
class KeywordedPhoto extends Photo
{
    #[\Override]
    public function getMorphClass(): string
    {
        return Photo::class;
    }

    /** @return MorphToMany<Keyword, $this> */
    public function keywords(): MorphToMany
    {
        return $this->morphToMany(Keyword::class, 'keywordable');
    }
}
