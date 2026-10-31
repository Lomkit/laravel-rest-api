<?php

namespace Lomkit\Rest\Instructions;

use Lomkit\Rest\Http\Requests\RestRequest;

trait Instructionable
{
    /** @return array<int, Instruction> */
    public function instructions(RestRequest $request): array
    {
        return [];
    }

    /** @return array<int, Instruction> */
    public function getInstructions(RestRequest $request): array
    {
        return $this->context()->definition($this)->instructions();
    }

    public function instructionExists(RestRequest $request, string $instructionKey): bool
    {
        return array_any($this->getInstructions($request), fn (Instruction $instruction): bool => $instruction->uriKey() === $instructionKey);
    }

    public function instruction(RestRequest $request, string $instructionKey): ?Instruction
    {
        return array_find($this->getInstructions($request), fn (Instruction $instruction): bool => $instruction->uriKey() === $instructionKey)?->resource($this);
    }
}
