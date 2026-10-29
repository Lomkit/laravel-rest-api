<?php

namespace Lomkit\Rest\Tests\Fixtures\Workspace;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Lomkit\Rest\Fields\Boolean;
use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;

class SqlOnlyTeamNoteResource extends Resource
{
    public static string $model = Note::class;

    public function fields(RestRequest $request): array
    {
        return [ID::make('id'), Text::make('team'), Text::make('title'), Boolean::make('pinned')];
    }

    public function searchQuery(RestRequest $request, Builder $query): Builder
    {
        return $query->where('team', 'red');
    }
}
