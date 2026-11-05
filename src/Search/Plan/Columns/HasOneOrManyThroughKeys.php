<?php

namespace Lomkit\Rest\Search\Plan\Columns;

use Illuminate\Database\Eloquent\Relations\HasOneOrManyThrough;

final readonly class HasOneOrManyThroughKeys extends RelationKeys
{
    /** @param HasOneOrManyThrough<*, *, *, *> $relation */
    public function __construct(private HasOneOrManyThrough $relation) {}

    #[\Override]
    public function parent(): array
    {
        return [$this->relation->getLocalKeyName()];
    }

    #[\Override]
    public function related(): array
    {
        return [];
    }
}
