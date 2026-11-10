<?php

namespace Lomkit\Rest\Exceptions;

use LogicException;
use Lomkit\Rest\Fields\MutationOperation;

final class InvalidRelationException extends LogicException
{
    public static function withdrawnDelete(string $type, string $relation): self
    {
        return new self("The {$type} relation [{$relation}] cannot withdraw the delete operation: a relation accepts delete only through deletable(). Leave deletable() off instead.");
    }

    /** @param list<MutationOperation> $supported */
    public static function unsupportedOperation(string $type, string $relation, MutationOperation $operation, array $supported): self
    {
        $operations = implode(', ', array_column($supported, 'value'));

        return new self("The {$type} relation [{$relation}] cannot withdraw the {$operation->value} operation: it does not accept it. Withdraw only the operations it accepts [{$operations}].");
    }
}
