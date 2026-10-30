<?php

namespace Lomkit\Rest\Tests\Fixtures\Workspace;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Laravel\Scout\Builder as ScoutBuilder;
use Lomkit\Rest\Fields\Boolean;
use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;

class WideIndexTeamNoteResource extends Resource
{
    public static string $model = Note::class;

    #[\Override]
    public function fields(RestRequest $request): array
    {
        return [ID::make('id'), Text::make('team'), Text::make('title'), Boolean::make('pinned')];
    }

    #[\Override]
    public function searchQuery(RestRequest $request, Builder $query): Builder
    {
        return $query->where('team', 'red');
    }

    /**
     * @param  ScoutBuilder<Note>  $query
     * @return ScoutBuilder<Note>
     */
    #[\Override]
    public function searchScoutQuery(RestRequest $request, ScoutBuilder $query): ScoutBuilder
    {
        return $query;
    }
}
