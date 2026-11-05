<?php

namespace Lomkit\Rest\Search\Plan\Columns;

final readonly class UnknownRelationKeys extends RelationKeys
{
    #[\Override]
    public function parent(): array
    {
        return ['*'];
    }

    #[\Override]
    public function related(): array
    {
        return ['*'];
    }
}
