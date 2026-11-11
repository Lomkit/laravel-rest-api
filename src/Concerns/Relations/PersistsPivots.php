<?php

namespace Lomkit\Rest\Concerns\Relations;

use Illuminate\Container\Container;
use Lomkit\Rest\Contracts\PivotPersister;
use Lomkit\Rest\Exceptions\InvalidRelationException;
use Lomkit\Rest\Mutations\EloquentPivots;

trait PersistsPivots
{
    private ?string $pivotPersister = null;

    final public function persistedBy(string $persister): static
    {
        if (! is_a($persister, PivotPersister::class, true)) {
            throw InvalidRelationException::notAPivotPersister(class_basename($this), $this->relation, $persister);
        }

        $this->pivotPersister = $persister;

        return $this;
    }

    final public function getPivotPersister(): PivotPersister
    {
        if ($this->pivotPersister === null) {
            return new EloquentPivots;
        }

        $persister = Container::getInstance()->make($this->pivotPersister);

        if (! $persister instanceof PivotPersister) {
            throw InvalidRelationException::notAPivotPersister(class_basename($this), $this->relation, $this->pivotPersister);
        }

        return $persister;
    }
}
