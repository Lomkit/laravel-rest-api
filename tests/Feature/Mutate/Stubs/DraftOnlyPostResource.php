<?php

namespace Lomkit\Rest\Tests\Feature\Mutate\Stubs;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Tests\Fixtures\Publishing\PostResource;
use Lomkit\Rest\Tests\Fixtures\Publishing\PostStatus;

class DraftOnlyPostResource extends PostResource
{
    /** @var list<class-string> */
    public static array $hooked = [];

    #[\Override]
    public function mutateQuery(RestRequest $request, Builder $query): Builder
    {
        self::$hooked[] = $request->resource::class;

        return $query->where('posts.status', PostStatus::Draft->value);
    }
}
