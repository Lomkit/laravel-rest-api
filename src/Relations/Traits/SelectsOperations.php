<?php

namespace Lomkit\Rest\Relations\Traits;

use Lomkit\Rest\Contracts\PivotRelation;
use Lomkit\Rest\Contracts\SyncableRelation;
use Lomkit\Rest\Fields\MutationOperation;
use Lomkit\Rest\Relations\OperationSelection;

trait SelectsOperations
{
    private ?OperationSelection $selection = null;

    final public function onlyOperations(MutationOperation ...$operations): static
    {
        $this->selection = $this->operationSelection()->only($this, ...$operations);

        return $this;
    }

    final public function withoutOperations(MutationOperation ...$operations): static
    {
        $this->selection = $this->operationSelection()->without($this, ...$operations);

        return $this;
    }

    final public function syncRequiresWithoutDetaching(): bool
    {
        return $this instanceof PivotRelation && $this->operationSelection()->keepsLinks($this->mutationOperations());
    }

    final public function writesRelatedRows(MutationOperation $operation): bool
    {
        if ($operation === MutationOperation::Sync && $this->syncsChildren()) {
            return false;
        }

        return $operation === MutationOperation::Create || ! $this->operationSelection()->withdraws(MutationOperation::Update, $this->mutationOperations());
    }

    final public function syncsChildren(): bool
    {
        return $this instanceof SyncableRelation && $this->isSyncable();
    }

    private function operationSelection(): OperationSelection
    {
        return $this->selection ??= OperationSelection::all();
    }
}
