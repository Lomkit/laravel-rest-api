<?php

namespace Lomkit\Rest\Tests\Fixtures\Publishing;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Lomkit\Rest\Fields\DateTime;
use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Json;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\BelongsToMany;
use Lomkit\Rest\Relations\HasMany;

class AuthorResource extends Resource
{
    public static string $model = Author::class;

    #[\Override]
    public function fields(RestRequest $request): array
    {
        return [
            ID::make('id')->sortable(false),
            Text::make('first_name'),
            Text::make('last_name'),
            Text::make('full_name')->virtual()
                ->sortUsing(fn (Builder $query, string $direction) => $query->orderBy('last_name', $direction)->orderBy('first_name', $direction))
                ->fillUsing(function (Model $author, mixed $value): void {
                    $author->forceFill(['first_name' => Str::before((string) $value, ' '), 'last_name' => Str::after((string) $value, ' ')]);
                }),
            Text::make('initials')->virtual(),
            Text::make('api_token')->writeOnly(),
            Json::make('settings')->nullable(),
            DateTime::make('created_at')->selectable(false)->readonly(),
        ];
    }

    #[\Override]
    public function relations(RestRequest $request): array
    {
        return [
            HasMany::make('posts', PostResource::class),
            HasMany::make('drafts', DraftResource::class),
            BelongsToMany::make('tags', TagResource::class),
        ];
    }
}
