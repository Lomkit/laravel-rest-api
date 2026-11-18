<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Lomkit\Rest\Instructions\AppliesToEngine;
use Lomkit\Rest\Instructions\Instruction;
use Lomkit\Rest\Instructions\InstructionFields;
use Lomkit\Rest\Text\Engine\EngineConditionGroup;
use Lomkit\Rest\Text\Engine\EngineQuery;

final class ReaderGroupInstruction extends Instruction implements AppliesToEngine
{
    #[\Override]
    public function uriKey(): string
    {
        return 'reader-group';
    }

    #[\Override]
    public function handleEngine(InstructionFields $fields, EngineQuery $query): void
    {
        $query->whereAny(fn (EngineConditionGroup $group): EngineConditionGroup => $group
            ->where('number', '=', 1)
            ->whereAll(fn (EngineConditionGroup $all): EngineConditionGroup => $all->where('price', '<', 5)->whereIn('readers', ['bob'])->whereNotIn('readers', ['cid'])));
    }
}
