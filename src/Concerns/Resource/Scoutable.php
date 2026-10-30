<?php

namespace Lomkit\Rest\Concerns\Resource;

use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Instructions\Instruction;

trait Scoutable
{
    /** @return list<string> */
    public function scoutFields(RestRequest $request): array
    {
        return [];
    }

    /** @return array<int, Instruction> */
    public function scoutInstructions(RestRequest $request): array
    {
        return [];
    }

    /** @return list<string> */
    public function getScoutFields(RestRequest $request): array
    {
        return $this->context()->definition($this)->scoutFields();
    }

    /** @return array<int, Instruction> */
    public function getScoutInstructions(RestRequest $request): array
    {
        return $this->context()->definition($this)->scoutInstructions();
    }

    /**
     * Retrieve a specific scout instruction by its key.
     *
     * @param  RestRequest  $request  The REST request instance.
     * @param  string  $instructionKey  The key of the instruction to retrieve.
     * @return Instruction|null The instruction instance or null if not found.
     */
    public function scoutInstruction(RestRequest $request, string $instructionKey)
    {
        $instruction = collect($this->getScoutInstructions($request))
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
