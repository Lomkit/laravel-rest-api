<?php

namespace Lomkit\Rest\Tests\Fixtures\Archive;

use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;

class SearchableDocumentResource extends Resource
{
    public static string $model = SearchableDocument::class;

    public function fields(RestRequest $request): array
    {
        return [ID::make('id'), Text::make('title')];
    }
}
