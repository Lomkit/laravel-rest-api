<?php

namespace Lomkit\Rest\Tests\Fixtures\Library;

use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\BelongsToMany;
use Lomkit\Rest\Relations\HasOne;
use Lomkit\Rest\Relations\HasOneOfMany;
use Lomkit\Rest\Relations\MorphMany;
use Lomkit\Rest\Relations\MorphOne;
use Lomkit\Rest\Relations\MorphOneOfMany;

class BookResource extends Resource
{
    public static string $model = Book::class;

    #[\Override]
    public function fields(RestRequest $request): array
    {
        return [ID::make('id'), Text::make('title')];
    }

    #[\Override]
    public function relations(RestRequest $request): array
    {
        return [
            HasOne::make('loan', LoanResource::class),
            HasOneOfMany::make('latestLoan', LoanResource::class),
            MorphOne::make('label', LabelResource::class),
            MorphOneOfMany::make('latestLabel', LabelResource::class),
            MorphMany::make('labels', LabelResource::class),
            BelongsToMany::make('shelves', ShelfResource::class),
        ];
    }
}
