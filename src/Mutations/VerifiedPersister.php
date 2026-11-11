<?php

namespace Lomkit\Rest\Mutations;

use Lomkit\Rest\Contracts\PivotPersister;
use Lomkit\Rest\Exceptions\PivotPersisterException;
use Lomkit\Rest\Relations\Relation;

final readonly class VerifiedPersister implements PivotPersister
{
    public function __construct(
        private PivotPersister $persister,
        private Relation $relation,
    ) {}

    #[\Override]
    public function attach(PivotWrite $write): void
    {
        $this->persister->attach($write);

        $keys = $write->keys();
        $missing = LinkedKeys::missing($keys, LinkedKeys::among($write->pivots(), $keys));

        if ($missing !== []) {
            throw PivotPersisterException::unattached($this->persister::class, $this->relation->parentResource()::class, $write->relation, $missing);
        }
    }

    #[\Override]
    public function detach(PivotWrite $write): void
    {
        $this->persister->detach($write);

        $linked = LinkedKeys::among($write->pivots(), $write->keys());

        if ($linked !== []) {
            throw PivotPersisterException::undetached($this->persister::class, $this->relation->parentResource()::class, $write->relation, $linked);
        }
    }

    #[\Override]
    public function updatePivot(PivotWrite $write): void
    {
        $this->persister->updatePivot($write);
    }
}
