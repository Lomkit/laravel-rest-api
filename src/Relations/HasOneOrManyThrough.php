<?php

namespace Lomkit\Rest\Relations;

abstract class HasOneOrManyThrough extends Relation
{
    #[\Override]
    public function mutationOperations(): array
    {
        return [];
    }
}
