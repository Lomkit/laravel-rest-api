<?php

namespace Lomkit\Rest\Instructions;

trait Instructionable
{
    /** @return array<int, Instruction> */
    public function instructions(): array
    {
        return [];
    }
}
