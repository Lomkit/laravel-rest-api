<?php

namespace Lomkit\Rest\Tests\Feature\Search\Stubs;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Lomkit\Rest\Tests\Fixtures\Publishing\Author;
use Lomkit\Rest\Tests\Fixtures\Publishing\Draft;
use Lomkit\Rest\Tests\Fixtures\Publishing\Post;
use Lomkit\Rest\Tests\Fixtures\Publishing\ReadableAuthorPolicy;

#[Table('authors')]
#[UsePolicy(ReadableAuthorPolicy::class)]
class LeadingAuthor extends Author
{
    public function draft(): HasOne
    {
        return $this->hasOne(Draft::class, 'author_id');
    }

    public function leadPost(): HasOne
    {
        return $this->hasOne(Post::class, 'author_id');
    }
}
