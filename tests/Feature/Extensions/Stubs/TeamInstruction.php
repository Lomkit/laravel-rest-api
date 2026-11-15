<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Illuminate\Database\Eloquent\Builder;
use Lomkit\Rest\Instructions\AppliesToEngine;
use Lomkit\Rest\Instructions\Instruction;
use Lomkit\Rest\Instructions\InstructionFields;
use Lomkit\Rest\Text\Engine\EngineQuery;

final class TeamInstruction extends Instruction implements AppliesToEngine
{
    #[\Override]
    public function uriKey(): string
    {
        return 'red-team';
    }

    #[\Override]
    public function handle(InstructionFields $fields, Builder $query): void
    {
        $query->where($query->qualifyColumn('team'), 'red');
    }

    #[\Override]
    public function handleEngine(InstructionFields $fields, EngineQuery $query): void
    {
        $query->whereIn('team', ['red']);
    }
}
