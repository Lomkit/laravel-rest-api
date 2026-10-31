<?php

namespace Lomkit\Rest\Tests\Fixtures\Workspace;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Lomkit\Rest\Fields\Boolean;
use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;

class TeamNoteResource extends Resource
{
    public static string $model = Note::class;

    #[\Override]
    public function fields(RestRequest $request): array
    {
        return [ID::make('id'), Text::make('team'), Text::make('title'), Boolean::make('pinned')];
    }

    #[\Override]
    public function actions(RestRequest $request): array
    {
        return [PinNotes::make()];
    }

    #[\Override]
    public function searchQuery(RestRequest $request, Builder $query): Builder
    {
        return $query->where('team', 'red');
    }
}
