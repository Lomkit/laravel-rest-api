<?php

namespace Lomkit\Rest\Fields;

final readonly class FieldRules
{
    /**
     * @param  list<mixed>  $shared
     * @param  list<mixed>  $creation
     * @param  list<mixed>  $update
     */
    public function __construct(private array $shared, private array $creation = [], private array $update = []) {}

    /** @return list<mixed> */
    public function shared(): array
    {
        return $this->shared;
    }

    /** @return list<mixed> */
    public function declaredFor(WriteOperation $operation): array
    {
        return match ($operation) {
            WriteOperation::Create => $this->creation,
            WriteOperation::Update => $this->update,
        };
    }

    /** @return list<mixed> */
    public function forWrite(WriteOperation $operation): array
    {
        return [...$this->shared, ...$this->declaredFor($operation)];
    }

    public function hasWriteRules(): bool
    {
        return $this->creation !== [] || $this->update !== [];
    }
}
