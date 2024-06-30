<?php

namespace Lomkit\Rest\Concerns\Resource;

use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Instructions\Instruction;

trait Scoutable
{
    /**
     * The calculated scout fields if already done in this request.
     *
     * @var array
     */
    protected array $calculatedScoutFields;

    /**
     * The calculated scout instructions if already done in this request.
     *
     * @var array
     */
    protected array $calculatedScoutInstructions;

    /**
     * The scout fields that could be provided.
     *
     * @param RestRequest $request
     *
     * @return array
     */
    public function scoutFields(RestRequest $request): array
    {
        return [];
    }

    /**
     * The scout instructions that could be provided.
     *
     * @param RestRequest $request
     *
     * @return array
     */
    public function scoutInstructions(RestRequest $request): array
    {
        return [];
    }

    /**
     * Get the resource's scout fields.
     *
     * @param \Lomkit\Rest\Http\Requests\RestRequest $request
     *
     * @return array
     */
    public function getScoutFields(\Lomkit\Rest\Http\Requests\RestRequest $request): array
    {
        return $this->calculatedScoutFields ?? ($this->calculatedScoutFields = $this->scoutFields($request));
    }

    /**
     * Get the resource's scout instructions.
     *
     * @param \Lomkit\Rest\Http\Requests\RestRequest $request
     *
     * @return array
     */
    public function getScoutInstructions(\Lomkit\Rest\Http\Requests\RestRequest $request): array
    {
        return $this->calculatedScoutInstructions ?? ($this->calculatedScoutInstructions = $this->scoutInstructions($request));
    }

    /**
     * Retrieve a specific scout instruction by its key.
     *
     * @param RestRequest $request        The REST request instance.
     * @param string      $instructionKey The key of the instruction to retrieve.
     *
     * @return Instruction|null The instruction instance or null if not found.
     */
    public function scoutInstruction(RestRequest $request, string $instructionKey)
    {
        $instruction = collect($this->getScoutInstructions($request))
            ->first(function (Instruction $instruction) use ($instructionKey) {
                return $instruction->uriKey() === $instructionKey;
            });

        if (!is_null($instruction)) {
            $instruction
                ->resource($this);
        }

        return $instruction;
    }
}
