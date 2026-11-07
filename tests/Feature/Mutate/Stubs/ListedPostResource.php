<?php

namespace Lomkit\Rest\Tests\Feature\Mutate\Stubs;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Lomkit\Rest\Tests\Fixtures\Publishing\PostResource;

class ListedPostResource extends PostResource
{
    /** @var list<string> */
    public static array $hooked = [];

    #[\Override]
    public function perimeterQuery(Builder $query): Builder
    {
        self::$hooked[] = $this->request()->resource::class.' '.$this->request()->endpoint()?->value;

        return $query->where('posts.title', '<>', 'Annual report');
    }
}
