<?php

namespace Lomkit\Rest\Tests\Feature\Text\Stubs;

use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Tests\Fixtures\Catalog\CategoryResource;
use Lomkit\Rest\Text\Contains;
use Lomkit\Rest\Text\TextSearch;

class ContainsCategoryResource extends CategoryResource
{
    #[\Override]
    public function fields(): array
    {
        return [ID::make('id'), Text::make('name')->searchable()];
    }

    #[\Override]
    public function text(): TextSearch
    {
        return Contains::make();
    }
}
