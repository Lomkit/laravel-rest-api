<?php

namespace Lomkit\Rest\Tests\Feature\Mutate\Stubs;

use Lomkit\Rest\Fields\MutationOperation;
use Lomkit\Rest\Relations\HasOne;
use Lomkit\Rest\Relations\MorphOne;

class LockedBookResource extends PerimeterBookResource
{
    #[\Override]
    public function relations(): array
    {
        return [
            HasOne::make('loan', PerimeterLoanResource::class)
                ->deletable()
                ->withoutOperations(MutationOperation::Detach, MutationOperation::Update),
            MorphOne::make('label', PerimeterLabelResource::class)->withoutOperations(MutationOperation::Detach, MutationOperation::Update),
        ];
    }
}
