<?php

namespace Lomkit\Rest\Tests\Fixtures\Publishing;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Lomkit\Rest\Fields\DateTime;
use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Json;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\HasMany;

class AuthorResource extends Resource
{
    public static string $model = Author::class;

    public function fields(RestRequest $request): array
    {
        return [
            ID::make('id')->sortable(false),
            Text::make('first_name'),
            Text::make('last_name'),
            Text::make('full_name')->virtual()
                ->sortUsing(fn (Builder $query, string $direction) => $query->orderBy('last_name', $direction)->orderBy('first_name', $direction)),
            Text::make('initials')->virtual(),
            Text::make('api_token')->writeOnly(),
            Json::make('settings')->nullable(),
            DateTime::make('created_at')->selectable(false),
        ];
    }

    public function relations(RestRequest $request): array
    {
        return [
            HasMany::make('posts', PostResource::class),
            HasMany::make('drafts', DraftResource::class),
        ];
    }
}
