<?php

namespace Lomkit\Rest\Tests\Feature\Deletion\Stubs;

use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;

class SecondaryDocumentResource extends Resource
{
    public static string $model = SecondaryDocument::class;

    #[\Override]
    public function fields(RestRequest $request): array
    {
        return [ID::make('id'), Text::make('title')];
    }
}
