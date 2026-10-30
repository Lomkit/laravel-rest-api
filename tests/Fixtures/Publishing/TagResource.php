<?php

namespace Lomkit\Rest\Tests\Fixtures\Publishing;

use Lomkit\Rest\Fields\DateTime;
use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;

class TagResource extends Resource
{
    public static string $model = Tag::class;

    #[\Override]
    public function fields(RestRequest $request): array
    {
        return [
            ID::make('id'),
            Text::make('name'),
            Text::make('slug')->updatable(false),
            DateTime::make('archived_at')->nullable()->creatable(false),
            DateTime::make('created_at')->readonly(),
        ];
    }

    #[\Override]
    public function updateRules(RestRequest $request): array
    {
        return [
            'name' => 'max:20',
        ];
    }
}
