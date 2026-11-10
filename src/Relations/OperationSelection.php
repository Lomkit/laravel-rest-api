<?php

namespace Lomkit\Rest\Relations;

use Lomkit\Rest\Exceptions\InvalidRelationException;
use Lomkit\Rest\Fields\MutationOperation;

final readonly class OperationSelection
{
    /**
     * @param  array<string, MutationOperation>|null  $kept
     * @param  array<string, MutationOperation>  $withdrawn
     */
    private function __construct(private ?array $kept, private array $withdrawn) {}

    public static function all(): self
    {
        return new self(null, []);
    }

    public function only(Relation $relation, MutationOperation ...$operations): self
    {
        $kept = self::checked($relation, true, $operations);

        return new self($this->kept === null ? $kept : array_intersect_key($this->kept, $kept), $this->withdrawn);
    }

    public function without(Relation $relation, MutationOperation ...$operations): self
    {
        return new self($this->kept, [...$this->withdrawn, ...self::checked($relation, false, $operations)]);
    }

    /**
     * @param  list<MutationOperation>  $supported
     * @return list<MutationOperation>
     */
    public function from(array $supported, bool $pivot): array
    {
        $accepted = array_values(array_filter($supported, fn (MutationOperation $operation): bool => $this->accepts($operation)));

        if (! $pivot || ! $this->keepsLinks($supported)) {
            return $accepted;
        }

        return array_values(array_filter($accepted, fn (MutationOperation $operation): bool => $operation !== MutationOperation::Toggle));
    }

    /** @param list<MutationOperation> $supported */
    public function keepsLinks(array $supported): bool
    {
        return in_array(MutationOperation::Detach, $supported, true) && ! $this->accepts(MutationOperation::Detach);
    }

    private function accepts(MutationOperation $operation): bool
    {
        return ($this->kept === null || isset($this->kept[$operation->value])) && ! isset($this->withdrawn[$operation->value]);
    }

    /**
     * @param  array<MutationOperation>  $operations
     * @return array<string, MutationOperation>
     */
    private static function checked(Relation $relation, bool $keep, array $operations): array
    {
        $type = class_basename($relation);
        $checked = [];

        foreach ($operations as $operation) {
            if ($operation === MutationOperation::Delete) {
                throw InvalidRelationException::deleteOperation($type, $relation->relation, $keep);
            }

            if (! in_array($operation, $relation->mutationOperations(), true)) {
                throw InvalidRelationException::unsupportedOperation($type, $relation->relation, $keep, $operation, $relation->mutationOperations());
            }

            $checked[$operation->value] = $operation;
        }

        return $checked;
    }
}
