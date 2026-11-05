<?php

namespace Lomkit\Rest\Search\Plan\Columns;

use Illuminate\Database\Eloquent\Relations\MorphOneOrMany;

final readonly class MorphOneOrManyKeys extends RelationKeys
{
    /** @param MorphOneOrMany<*, *, *> $relation */
    public function __construct(private MorphOneOrMany $relation) {}

    #[\Override]
    public function parent(): array
    {
        return [$this->relation->getLocalKeyName()];
    }

    #[\Override]
    public function related(): array
    {
        return [$this->relation->getForeignKeyName(), $this->relation->getMorphType()];
    }
}
