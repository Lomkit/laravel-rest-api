<?php

namespace Lomkit\Rest\Tests\Feature\Mutate\Stubs;

use Lomkit\Rest\Fields\MutationOperation;
use Lomkit\Rest\Relations\HasOne;
use Lomkit\Rest\Relations\MorphOne;

class KeptBookResource extends PerimeterBookResource
{
    #[\Override]
    public function relations(): array
    {
        return [
            HasOne::make('loan', PerimeterLoanResource::class)->withoutOperations(MutationOperation::Detach),
            MorphOne::make('label', PerimeterLabelResource::class)
                ->deletable()
                ->withoutOperations(MutationOperation::Detach),
        ];
    }
}
