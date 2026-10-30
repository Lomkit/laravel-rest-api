<?php

namespace Lomkit\Rest\Tests\Fixtures\Library;

use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\MorphTo;

class LabelResource extends Resource
{
    public static string $model = Label::class;

    #[\Override]
    public function fields(RestRequest $request): array
    {
        return [
            ID::make('id'),
            Text::make('text'),
            Integer::make('labelable_id')->nullable(),
            Text::make('labelable_type')->nullable(),
        ];
    }

    #[\Override]
    public function relations(RestRequest $request): array
    {
        return [MorphTo::make('labelable', BookResource::class)];
    }
}
