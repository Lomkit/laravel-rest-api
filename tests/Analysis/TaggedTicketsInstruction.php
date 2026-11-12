<?php

namespace Lomkit\Rest\Tests\Analysis;

use Illuminate\Database\Eloquent\Builder;
use Lomkit\Rest\Instructions\Instruction;
use Lomkit\Rest\Instructions\InstructionFields;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Ticket;

/** @extends Instruction<Ticket> */
final class TaggedTicketsInstruction extends Instruction
{
    /** @param Builder<Ticket> $query */
    #[\Override]
    public function handle(InstructionFields $fields, Builder $query): void
    {
        $query->whereHas(
            $query
                ->getModel()
                ->tags()
                ->getRelationName()
        );
    }
}
