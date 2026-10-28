<?php

namespace Lomkit\Rest\Instructions;

use Lomkit\Rest\Http\Requests\RestRequest;

trait Instructionable
{
    /**
     * The calculated instructions if already done in this request.
     */
    protected array $calculatedInstructions;

    /**
     * The instructions that should be linked.
     */
    public function instructions(RestRequest $request): array
    {
        return [];
    }

    /**
     * Get the resource's instructions.
     */
    public function getInstructions(RestRequest $request): array
    {
        return $this->calculatedInstructions ??= array_map(fn (Instruction $instruction) => $instruction->resource($this), $this->instructions($request));
    }

    /**
     * Check if a specific instruction exists.
     *
     * @param  RestRequest  $request  The REST request instance.
     * @param  string  $instructionKey  The key of the instruction to check.
     * @return bool True if the instruction exists; otherwise, false.
     */
    public function instructionExists(RestRequest $request, string $instructionKey): bool
    {
        return collect($this->getInstructions($request))
            ->contains(function (Instruction $instruction) use ($instructionKey) {
                return $instruction->uriKey() === $instructionKey;
            });
    }

    /**
     * Retrieve a specific instruction by its key.
     *
     * @param  RestRequest  $request  The REST request instance.
     * @param  string  $instructionKey  The key of the instruction to retrieve.
     * @return Instruction|null The instruction instance or null if not found.
     */
    public function instruction(RestRequest $request, string $instructionKey)
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
