<?php

namespace Lomkit\Rest\Instructions;

trait Instructionable
{
    /** @return array<int, Instruction|class-string<Instruction>> */
    public function instructions(): array
    {
        return [];
    }
}
