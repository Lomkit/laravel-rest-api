<?php

namespace Lomkit\Rest\Search\Plan\Columns;

use Illuminate\Database\Eloquent\Relations\HasOneOrMany;

final readonly class HasOneOrManyKeys extends RelationKeys
{
    /** @param HasOneOrMany<*, *, *> $relation */
    public function __construct(private HasOneOrMany $relation) {}

    #[\Override]
    public function parent(): array
    {
        return [$this->relation->getLocalKeyName()];
    }

    #[\Override]
    public function related(): array
    {
        return [$this->relation->getForeignKeyName()];
    }
}
