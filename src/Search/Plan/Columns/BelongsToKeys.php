<?php

namespace Lomkit\Rest\Search\Plan\Columns;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

final readonly class BelongsToKeys extends RelationKeys
{
    /** @param BelongsTo<*, *> $relation */
    public function __construct(private BelongsTo $relation) {}

    #[\Override]
    public function parent(): array
    {
        return [$this->relation->getForeignKeyName()];
    }

    #[\Override]
    public function related(): array
    {
        return [$this->relation->getOwnerKeyName()];
    }
}
