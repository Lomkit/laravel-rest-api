<?php

namespace Lomkit\Rest\Tests\Feature\Scaffolding\Stubs;

use Lomkit\Rest\Attributes\WithoutPerimeter;
use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Tests\Fixtures\Library\Book;
use Lomkit\Rest\Text\ScoutEngine;
use Lomkit\Rest\Text\TextSearch;

#[WithoutPerimeter]
final class IndexedBookResource extends Resource
{
    public static string $model = Book::class;

    #[\Override]
    public function fields(): array
    {
        return [ID::make('id'), Text::make('title')->searchable()];
    }

    #[\Override]
    public function text(): ?TextSearch
    {
        return ScoutEngine::make()->withoutPerimeter();
    }
}
