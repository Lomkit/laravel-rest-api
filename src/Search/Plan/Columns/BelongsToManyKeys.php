<?php

namespace Lomkit\Rest\Search\Plan\Columns;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;

final readonly class BelongsToManyKeys extends RelationKeys
{
    /** @param BelongsToMany<*, *> $relation */
    public function __construct(private BelongsToMany $relation) {}

    #[\Override]
    public function parent(): array
    {
        return [$this->relation->getParentKeyName()];
    }

    #[\Override]
    public function related(): array
    {
        return [];
    }
}
