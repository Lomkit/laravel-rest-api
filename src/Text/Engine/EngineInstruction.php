<?php

namespace Lomkit\Rest\Text\Engine;

use Lomkit\Rest\Exceptions\InvalidInstructionException;
use Lomkit\Rest\Fields\CastValues;
use Lomkit\Rest\Instructions\AppliesToEngine;
use Lomkit\Rest\Search\InstructionNode;

final readonly class EngineInstruction
{
    private function __construct(
        private AppliesToEngine $instruction,
        private CastValues $fields,
    ) {}

    public static function of(InstructionNode $node): self
    {
        if (! $node->instruction instanceof AppliesToEngine) {
            throw InvalidInstructionException::queryOnly($node->instruction::class);
        }

        return new self($node->instruction, CastValues::of($node->fields, $node->instruction::class.'::fields()'));
    }

    public function apply(EngineQuery $query): void
    {
        $this->instruction->handleEngine($this->fields->toArray(), $query);
    }
}
