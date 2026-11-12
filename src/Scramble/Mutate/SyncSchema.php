<?php

namespace Lomkit\Rest\Scramble\Mutate;

use Dedoc\Scramble\Support\Generator\Types\BooleanType;
use Dedoc\Scramble\Support\Generator\Types\Type;
use Lomkit\Rest\Fields\MutationOperation;
use Lomkit\Rest\Relations\Relation;

final readonly class SyncSchema
{
    public static function withoutDetaching(Relation $relation, MutationOperation $operation): ?Type
    {
        if ($operation !== MutationOperation::Sync || $relation->syncsChildren()) {
            return null;
        }

        if ($relation->syncRequiresWithoutDetaching()) {
            return (new BooleanType)
                ->const(true)
                ->setDescription('Must be true: the relation does not detach rows, so the rows that `key` does not list stay attached');
        }

        return (new BooleanType)->setDescription('When true, the rows that `key` does not list stay attached');
    }

    public static function children(Relation $relation): string
    {
        if (! $relation->syncsChildren()) {
            return '';
        }

        return 'A `sync` entry lists the children to keep and comes once, next to `create` entries, `update` entries of the keys that it lists, and `delete` or `detach` entries of other keys. It removes nothing: when a child that the user can see is neither listed nor removed by these entries, the request answers 409 and writes nothing.';
    }
}
