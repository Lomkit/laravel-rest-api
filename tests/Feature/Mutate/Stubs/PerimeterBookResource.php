<?php

namespace Lomkit\Rest\Tests\Feature\Mutate\Stubs;

use Lomkit\Rest\Relations\BelongsToMany;
use Lomkit\Rest\Relations\HasOne;
use Lomkit\Rest\Relations\HasOneOfMany;
use Lomkit\Rest\Relations\MorphMany;
use Lomkit\Rest\Relations\MorphOne;
use Lomkit\Rest\Relations\MorphOneOfMany;
use Lomkit\Rest\Tests\Fixtures\Library\BookResource;

class PerimeterBookResource extends BookResource
{
    use HidesNamedRows;

    #[\Override]
    public function relations(): array
    {
        return [
            HasOne::make('loan', PerimeterLoanResource::class),
            HasOneOfMany::make('latestLoan', PerimeterLoanResource::class),
            MorphOne::make('label', PerimeterLabelResource::class),
            MorphOneOfMany::make('latestLabel', PerimeterLabelResource::class),
            MorphMany::make('labels', PerimeterLabelResource::class),
            BelongsToMany::make('shelves', PerimeterShelfResource::class),
        ];
    }

    protected static function nameColumn(): string
    {
        return 'books.title';
    }
}
