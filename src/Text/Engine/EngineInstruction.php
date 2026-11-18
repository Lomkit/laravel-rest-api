<?php

namespace Lomkit\Rest\Text\Engine;

use Lomkit\Rest\Exceptions\InvalidInstructionException;
use Lomkit\Rest\Instructions\AppliesToEngine;
use Lomkit\Rest\Instructions\Instruction;
use Lomkit\Rest\Instructions\InstructionFields;
use Lomkit\Rest\Search\InstructionNode;

final readonly class EngineInstruction
{
    private function __construct(
        private Instruction&AppliesToEngine $instruction,
        private InstructionFields $fields,
    ) {}

    public static function of(InstructionNode $node): self
    {
        if (! $node->instruction instanceof AppliesToEngine) {
            throw InvalidInstructionException::queryOnly($node->instruction::class);
        }

        return new self($node->instruction, InstructionFields::of($node->instruction, $node->fields));
    }

    public function apply(EngineQuery $query): void
    {
        $query->instructedBy($this->instruction);

        try {
            $this->instruction->handleEngine($this->fields, $query);
        } finally {
            $query->instructedBy(null);
        }
    }
}
