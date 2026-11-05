<?php

namespace Lomkit\Rest\Tests\Feature\Search\Stubs;

use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Tests\Fixtures\Newsroom\Summary;

class NewsSummaryResource extends Resource
{
    public static string $model = Summary::class;

    #[\Override]
    public function fields(): array
    {
        return [ID::make('id'), Text::make('body')];
    }
}
