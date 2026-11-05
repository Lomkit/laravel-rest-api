<?php

namespace Lomkit\Rest\Tests\Feature\Search\Stubs;

use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Tests\Fixtures\Newsroom\Reporter;

class NewsReporterResource extends Resource
{
    public static string $model = Reporter::class;

    #[\Override]
    public function fields(): array
    {
        return [ID::make('id'), Text::make('name')];
    }
}
