<?php

namespace Lomkit\Rest\Tests\Fixtures\Lab;

use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Json;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;

class RecorderResource extends Resource
{
    public static string $model = Recorder::class;

    #[\Override]
    public function fields(RestRequest $request): array
    {
        return [
            ID::make('id'),
            Text::make('name'),
            Json::make('profile')->nullable(),
            Json::make('samples')->nullable(),
            Json::make('limits')->nullable(),
            Json::make('notes')->nullable(),
        ];
    }
}
