<?php

namespace Lomkit\Rest\Tests\Feature\Search\Stubs\Illustrated;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\MorphToMany;

class KeywordedPhotoResource extends Resource
{
    public static string $model = KeywordedPhoto::class;

    #[\Override]
    public function fields(): array
    {
        return [ID::make('id'), Text::make('path')];
    }

    #[\Override]
    public function relations(): array
    {
        return [MorphToMany::make('keywords', NewsKeywordResource::class)];
    }

    #[\Override]
    public function perimeterQuery(Builder $query): Builder
    {
        return $query->where('photos.path', '!=', 'hidden.png');
    }
}
