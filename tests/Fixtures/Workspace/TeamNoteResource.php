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
    public function fields(): array
    {
        return [ID::make('id'), Text::make('team'), Text::make('title'), Boolean::make('pinned')];
    }

    #[\Override]
    public function actions(): array
    {
        return [PinNotes::make(), PinNamedNotes::make()];
    }

    #[\Override]
    public function perimeterQuery(RestRequest $request, Builder $query): Builder
    {
        return $query->where('team', 'red');
    }
}
