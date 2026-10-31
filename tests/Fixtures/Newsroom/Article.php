<?php

namespace Lomkit\Rest\Tests\Fixtures\Newsroom;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Guarded(['internal_token'])]
#[Hidden(['editor_token', 'internal_token'])]
#[UsePolicy(OpenNewsroomPolicy::class)]
class Article extends Model
{
    use SoftDeletes;

    #[\Override]
    protected function casts(): array
    {
        return [
            'status' => ArticleStatus::class,
            'options' => 'array',
            'featured' => 'boolean',
            'published_on' => 'date',
            'published_at' => 'datetime',
            'embargoed_until' => 'immutable_datetime',
        ];
    }
}
