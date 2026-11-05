<?php

namespace Lomkit\Rest\Mutations;

use Illuminate\Database\Eloquent\Model;
use LogicException;

final readonly class NoWrites extends RelationWrites
{
    #[\Override]
    public function after(Model $parent, Mutator $mutator, OperationLog $log): void
    {
        throw new LogicException("The relation [{$this->relation->relation}] cannot be written.");
    }
}
