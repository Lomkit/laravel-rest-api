<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Lomkit\Rest\Instructions\AppliesToEngine;
use Lomkit\Rest\Instructions\Instruction;
use Lomkit\Rest\Instructions\InstructionFields;
use Lomkit\Rest\Text\Engine\EngineConditionGroup;
use Lomkit\Rest\Text\Engine\EngineQuery;

final class EitherInstruction extends Instruction implements AppliesToEngine
{
    #[\Override]
    public function uriKey(): string
    {
        return 'either';
    }

    #[\Override]
    public function handleEngine(InstructionFields $fields, EngineQuery $query): void
    {
        EngineLog::$calls[] = $query->filtersWithGroups() ? 'instruction groups' : 'instruction without groups';

        if (! $query->filtersWithGroups()) {
            $query->whereIn('number', []);

            return;
        }

        $query->whereAny(fn (EngineConditionGroup $group): EngineConditionGroup => $group
            ->where('number', '=', 5)
            ->whereIn('number', [4])
            ->whereAll(fn (EngineConditionGroup $all): EngineConditionGroup => $all->where('active', '=', true)->where('price', '>', 20)));
    }
}
