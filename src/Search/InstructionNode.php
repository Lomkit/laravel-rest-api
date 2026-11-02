<?php

namespace Lomkit\Rest\Search;

use Lomkit\Rest\Fields\InputValues;
use Lomkit\Rest\Instructions\Instruction;

final readonly class InstructionNode
{
    public function __construct(
        public Instruction $instruction,
        public InputValues $fields,
    ) {}
}
