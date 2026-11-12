<?php

namespace Lomkit\Rest\Scramble\Mutate;

use Dedoc\Scramble\Support\Generator\Types\BooleanType;
use Dedoc\Scramble\Support\Generator\Types\Type;
use Lomkit\Rest\Fields\MutationOperation;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\Relation;
use Lomkit\Rest\Scramble\Components\KeySchema;
use Lomkit\Rest\Scramble\Schemas;
use Lomkit\Rest\Scramble\Types\OneOf;

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

    public static function keys(Relation $relation, Resource $related, MutationOperation $operation): ?Type
    {
        if ($operation !== MutationOperation::Sync || ! $relation->syncsChildren()) {
            return null;
        }

        $keys = Schemas::list(KeySchema::value($related));
        $keys->setMax($related->context()->limits()->sizes->values);

        return (new OneOf([KeySchema::value($related), $keys]))->setDescription('The keys of the children to keep, a key or a list: with the `delete` and `detach` entries next to the sync, they must account for every child that the user can see');
    }

    public static function children(Relation $relation): string
    {
        if (! $relation->syncsChildren()) {
            return '';
        }

        return 'A `sync` entry lists the children to keep and comes once, next to `create` entries, `update` entries of the keys that it lists, and `delete` or `detach` entries of other keys. It removes nothing: when a child that the user can see is neither listed nor removed by these entries, the request answers 409 and writes nothing.';
    }
}
