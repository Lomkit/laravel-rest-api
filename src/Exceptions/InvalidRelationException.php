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
        $operations = implode(', ', array_column($supported, 'value'));
        $action = ucfirst($verb);

        return new self("The {$type} relation [{$relation}] cannot {$verb} the {$operation->value} operation: it does not accept it. {$action} only the operations it accepts [{$operations}].");
    }
}
