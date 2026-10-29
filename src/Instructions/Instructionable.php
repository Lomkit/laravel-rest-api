<?php

namespace Lomkit\Rest\Instructions;

use Lomkit\Rest\Http\Requests\RestRequest;

trait Instructionable
{
    /** @var array<int, Instruction> */
    protected array $calculatedInstructions;

    /** @return array<int, Instruction> */
    public function instructions(RestRequest $request): array
    {
        return [];
    }

    /** @return array<int, Instruction> */
    public function getInstructions(RestRequest $request): array
    {
        return $this->calculatedInstructions ??= array_map(fn (Instruction $instruction) => $instruction->resource($this), $this->instructions($request));
    }

    public function instructionExists(RestRequest $request, string $instructionKey): bool
    {
        return collect($this->getInstructions($request))
            ->contains(function (Instruction $instruction) use ($instructionKey) {
                return $instruction->uriKey() === $instructionKey;
            });
    }

    public function instruction(RestRequest $request, string $instructionKey): ?Instruction
    {
        $instruction = collect($this->getInstructions($request))
            ->first(function (Instruction $instruction) use ($instructionKey) {
                return $instruction->uriKey() === $instructionKey;
            });

        if (! is_null($instruction)) {
            $instruction
                ->resource($this);
        }

        return $instruction;
    }
}
