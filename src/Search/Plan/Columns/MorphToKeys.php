<?php

namespace Lomkit\Rest\Search\Plan\Columns;

use Illuminate\Database\Eloquent\Relations\MorphTo;

final readonly class MorphToKeys extends RelationKeys
{
    /** @param MorphTo<*, *> $relation */
    public function __construct(private MorphTo $relation) {}

    #[\Override]
    public function parent(): array
    {
        return [$this->relation->getMorphType(), $this->relation->getForeignKeyName()];
    }

    #[\Override]
    public function related(): array
    {
        return array_filter([$this->relation->getOwnerKeyName()]);
    }
}
