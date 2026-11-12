<?php

namespace Lomkit\Rest\Exceptions;

use LogicException;
use Lomkit\Rest\Fields\MutationOperation;

final class InvalidRelationException extends LogicException
{
    public static function deleteOperation(string $type, string $relation, bool $keep): self
    {
        $verb = $keep ? 'keep' : 'withdraw';
        $advice = $keep ? 'Declare deletable() instead.' : 'Leave deletable() off instead.';

        return new self("The {$type} relation [{$relation}] cannot {$verb} the delete operation: a relation accepts delete only through deletable(). {$advice}");
    }

    /** @param list<MutationOperation> $supported */
    public static function unsupportedOperation(string $type, string $relation, bool $keep, MutationOperation $operation, array $supported): self
    {
        $verb = $keep ? 'keep' : 'withdraw';
        $prefix = "The {$type} relation [{$relation}] cannot {$verb} the {$operation->value} operation:";

        if ($supported === []) {
            return new self("{$prefix} it accepts no operation.");
        }

        $operations = implode(', ', array_column($supported, 'value'));
        $action = ucfirst($verb);

        return new self("{$prefix} it does not accept it. {$action} only the operations it accepts [{$operations}].");
    }

    public static function toggleWithoutDetach(string $type, string $relation): self
    {
        return new self("The {$type} relation [{$relation}] cannot keep toggle without detach: a toggle detaches the attached rows that it names. Keep detach too, or leave toggle out.");
    }

    public static function syncOperation(string $type, string $relation, bool $keep): self
    {
        $verb = $keep ? 'keep' : 'withdraw';
        $advice = $keep ? 'Declare syncable() instead.' : 'Leave syncable() off instead.';

        return new self("The {$type} relation [{$relation}] cannot {$verb} the sync operation: a has-many relation accepts sync only through syncable(). {$advice}");
    }

    public static function syncAlreadyAccepted(string $type, string $relation): self
    {
        return new self("The {$type} relation [{$relation}] cannot declare syncable(): its mutationOperations() already list sync, which links the rows it names and removes none. Leave sync out of mutationOperations(), or leave syncable() off.");
    }

    public static function notAPivotPersister(string $type, string $relation, string $persister): self
    {
        return new self("The {$type} relation [{$relation}] cannot be persisted by [{$persister}]: persistedBy() takes a class that implements Lomkit\\Rest\\Contracts\\PivotPersister.");
    }
}
