<?php

namespace Lomkit\Rest\Tests\Fixtures\Archive;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Lomkit\Rest\Fields\Boolean;
use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;

class DocumentResource extends Resource
{
    public static $model = Document::class;

    public function fields(RestRequest $request): array
    {
        return [
            ID::make('id'),
            Text::make('title')->sortUsing(fn (Builder $query, string $direction) => $query->orderBy('title', $direction)),
            Boolean::make('archived'),
        ];
    }

    public function actions(RestRequest $request): array
    {
        return [ArchiveDocumentsAction::make(), QueuedArchiveDocumentsAction::make()];
    }
}
