<?php

namespace Lomkit\Rest\Tests\Feature\Search\Stubs;

use Illuminate\Database\Eloquent\Builder;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Tests\Fixtures\Publishing\Post;

class HeadlinedPostResource extends Resource
{
    public static string $model = Post::class;

    public function fields(RestRequest $request): array
    {
        return [
            Text::make('title'),
            Text::make('headline')->virtual()
                ->sortUsing(fn (Builder $query, string $direction) => $query->orderBy('title', $direction)),
        ];
    }
}
