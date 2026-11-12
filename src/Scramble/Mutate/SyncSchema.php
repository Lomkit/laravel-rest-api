<?php

namespace Lomkit\Rest\Scramble\Mutate;

use Dedoc\Scramble\Support\Generator\Types\BooleanType;
use Dedoc\Scramble\Support\Generator\Types\Type;
use Lomkit\Rest\Fields\MutationOperation;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\ChildRemoval;
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

    public static function children(Resource $resource, Relation $relation): string
    {
        $removal = ChildRemoval::of($relation);

        if ($removal === null) {
            return '';
        }

        $max = $resource->context()->limits()->totals->entries;
        $verb = $removal === ChildRemoval::Delete ? 'deletes' : 'detaches';

        return "A `sync` entry keeps the children that it lists and {$verb} the others that the related resource reaches, at most {$max}. It comes once, next to `create` entries and `update` entries of the keys that it lists.";
    }
}
