<?php

namespace Lomkit\Rest\Instructions;

use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Instructions\Instruction;

trait Instructionable
{
    /**
     * The instructions that should be linked
     * @param RestRequest $request
     * @return array
     */
    public function instructions(RestRequest $request): array {
        return [];
    }

    public function instructionExists(RestRequest $request, string $instructionKey): bool {
        return collect($this->instructions($request))
            ->contains(function (Instruction $instruction) use ($instructionKey) {
                return $instruction->uriKey() === $instructionKey;
            });
    }

    public function instruction(RestRequest $request, string $instructionKey) {
        $instruction = collect($this->instructions($request))
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