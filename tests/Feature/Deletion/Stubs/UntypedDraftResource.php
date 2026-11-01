<?php

namespace Lomkit\Rest\Tests\Feature\Deletion\Stubs;

use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;

class UntypedDraftResource extends Resource
{
    public static string $model = UntypedDraft::class;

    #[\Override]
    public function fields(RestRequest $request): array
    {
        return [
            ID::make('id')->keyType('uuid'),
            Text::make('title'),
        ];
    }
}
