<?php

namespace Lomkit\Rest\Relations;

use Lomkit\Rest\Concerns\Relations\HasPivotFields;
use Lomkit\Rest\Concerns\Relations\PersistsPivots;
use Lomkit\Rest\Contracts\PivotRelation;
use Lomkit\Rest\Fields\MutationOperation;
use Lomkit\Rest\Relations\Traits\HasMultipleResults;

class BelongsToMany extends Relation implements PivotRelation
{
    use HasMultipleResults;
    use HasPivotFields;
    use PersistsPivots;

    /** @return list<MutationOperation> */
    #[\Override]
    public function mutationOperations(): array
    {
        return [MutationOperation::Create, MutationOperation::Update, MutationOperation::Attach, MutationOperation::Detach, MutationOperation::Sync, MutationOperation::Toggle];
    }
}
