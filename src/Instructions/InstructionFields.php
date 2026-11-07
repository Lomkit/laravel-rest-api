<?php

namespace Lomkit\Rest\Instructions;

use Lomkit\Rest\Fields\CastValues;
use Lomkit\Rest\Fields\FieldValues;
use Lomkit\Rest\Fields\InputValues;

final readonly class InstructionFields extends FieldValues
{
    public static function of(Instruction $instruction, InputValues $inputs): self
    {
        return new self(CastValues::of($inputs, $instruction::class.'::fields()'));
    }
}
