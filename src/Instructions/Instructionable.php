<?php

namespace Lomkit\Rest\Instructions;

trait Instructionable
{
    /** @return array<int, Instruction> */
    public function instructions(): array
    {
        return [];
    }

    /** @return array<int, Instruction> */
    public function getInstructions(): array
    {
        return $this->context()->definition($this)->instructions();
    }

    public function instruction(string $instructionKey): ?Instruction
    {
        return array_find($this->getInstructions(), fn (Instruction $instruction): bool => $instruction->uriKey() === $instructionKey)?->resource($this);
    }
}
