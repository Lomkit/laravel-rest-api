<?php

namespace Lomkit\Rest\Relations;

use Lomkit\Rest\Exceptions\InvalidRelationException;
use Lomkit\Rest\Fields\MutationOperation;

final readonly class WithdrawnOperations
{
    /** @param array<string, MutationOperation> $operations */
    private function __construct(private array $operations) {}

    public static function none(): self
    {
        return new self([]);
    }

    public function with(Relation $relation, MutationOperation ...$operations): self
    {
        $withdrawn = $this->operations;
        $type = class_basename($relation);

        foreach ($operations as $operation) {
            if ($operation === MutationOperation::Delete) {
                throw InvalidRelationException::withdrawnDelete($type, $relation->relation);
            }

            if (! in_array($operation, $relation->mutationOperations(), true)) {
                throw InvalidRelationException::unsupportedOperation($type, $relation->relation, $operation, $relation->mutationOperations());
            }

            $withdrawn[$operation->value] = $operation;
        }

        return new self($withdrawn);
    }

    public function keepsLinks(): bool
    {
        return isset($this->operations[MutationOperation::Detach->value]);
    }

    /**
     * @param  list<MutationOperation>  $operations
     * @return list<MutationOperation>
     */
    public function from(array $operations, bool $pivot): array
    {
        $withdrawn = $pivot && $this->keepsLinks() ? [...array_values($this->operations), MutationOperation::Toggle] : array_values($this->operations);

        return array_values(array_filter($operations, fn (MutationOperation $operation): bool => ! in_array($operation, $withdrawn, true)));
    }
}
