<?php

namespace Lomkit\Rest\Relations;

use Lomkit\Rest\Contracts\PivotRelation;
use Lomkit\Rest\Fields\MutationOperation;

enum SyncMeaning: string
{
    case Check = 'check';
    case Replace = 'replace';
    case Link = 'link';

    public static function of(Relation $relation): ?self
    {
        if (! in_array(MutationOperation::Sync, $relation->acceptedOperations(), true)) {
            return null;
        }

        return match (true) {
            $relation->syncsChildren() => self::Check,
            $relation instanceof PivotRelation && ! $relation->syncRequiresWithoutDetaching() => self::Replace,
            default => self::Link,
        };
    }
}
