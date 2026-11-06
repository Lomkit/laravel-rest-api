<?php

namespace Lomkit\Rest\Tests\Feature\Mutate\Stubs;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Tests\Fixtures\Publishing\PostResource;

class ListedPostResource extends PostResource
{
    /** @var list<string> */
    public static array $searched = [];

    #[\Override]
    public function perimeterQuery(RestRequest $request, Builder $query): Builder
    {
        self::$searched[] = $request->resource::class.' '.$request->endpoint()?->value;

        return $query->where('posts.title', '<>', 'Annual report');
    }
}
