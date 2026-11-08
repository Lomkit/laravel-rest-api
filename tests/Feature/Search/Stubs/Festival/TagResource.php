<?php

namespace Lomkit\Rest\Tests\Feature\Search\Stubs\Festival;

use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\MorphedByMany;

class TagResource extends Resource
{
    public static string $model = Tag::class;

    #[\Override]
    public function fields(): array
    {
        return [ID::make('id'), Text::make('name')];
    }

    #[\Override]
    public function relations(): array
    {
        return [
            MorphedByMany::make('shows', ShowResource::class),
            MorphedByMany::make('stages', StageResource::class),
        ];
    }
}
