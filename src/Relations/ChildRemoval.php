<?php

namespace Lomkit\Rest\Relations;

use Lomkit\Rest\Contracts\SyncableRelation;
use Lomkit\Rest\Fields\MutationOperation;

enum ChildRemoval: string
{
    case Detach = 'detach';
    case Delete = 'delete';

    public static function of(Relation $relation): ?self
    {
        return $relation instanceof SyncableRelation ? $relation->syncRemoval() : null;
    }

    public function operation(): MutationOperation
    {
        return match ($this) {
            self::Detach => MutationOperation::Detach,
            self::Delete => MutationOperation::Delete,
        };
    }
}
