<?php

namespace Lomkit\Rest\Tests\Fixtures\Library;

use Lomkit\Rest\Attributes\WithoutPerimeter;
use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\BelongsTo;

#[WithoutPerimeter]
class LoanResource extends Resource
{
    public static string $model = Loan::class;

    #[\Override]
    public function fields(): array
    {
        return [ID::make('id'), Text::make('borrower'), Integer::make('book_id')->nullable()];
    }

    #[\Override]
    public function relations(): array
    {
        return [BelongsTo::make('book', BookResource::class)];
    }
}
