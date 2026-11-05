<?php

namespace Lomkit\Rest\Tests\Feature\Search\Stubs\Illustrated;

use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Tests\Fixtures\Newsroom\Keyword;

class NewsKeywordResource extends Resource
{
    public static string $model = Keyword::class;

    #[\Override]
    public function fields(): array
    {
        return [ID::make('id'), Text::make('name')];
    }
}
