<?php

namespace Lomkit\Rest\Tests\Feature\Validation\Stubs;

use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Http\Resource;

class UntypedDraftResource extends Resource
{
    public static string $model = UntypedDraft::class;

    #[\Override]
    public function fields(): array
    {
        return [
            ID::make('id')->keyType('uuid'),
            Text::make('title'),
        ];
    }

    #[\Override]
    public function actions(): array
    {
        return [RetitleUntypedDrafts::make(), CountUntypedDrafts::make()];
    }
}
