<?php

namespace Lomkit\Rest\Tests\Analysis;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\Eloquent\Builder;
use Lomkit\Rest\Instructions\Instruction;
use Lomkit\Rest\Instructions\InstructionFields;

final class RankTicketsInstruction extends Instruction
{
    public function __construct(private readonly Repository $config) {}

    #[\Override]
    public function handle(InstructionFields $fields, Builder $query): void
    {
        $query->orderBy($this->config->string('tickets.rank_column'), $fields->direction === 'asc' ? 'asc' : 'desc');
    }
}
