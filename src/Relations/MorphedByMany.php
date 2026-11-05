<?php

namespace Lomkit\Rest\Relations;

use Lomkit\Rest\Concerns\Relations\HasPivotFields;
use Lomkit\Rest\Contracts\PivotRelation;
use Lomkit\Rest\Fields\MutationOperation;
use Lomkit\Rest\Relations\Traits\HasMultipleResults;

class MorphedByMany extends MorphRelation implements PivotRelation
{
    use HasMultipleResults;
    use HasPivotFields;

    /** @return list<MutationOperation> */
    #[\Override]
    public function mutationOperations(): array
    {
        return MutationOperation::cases();
    }
}
